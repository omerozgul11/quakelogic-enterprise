<?php

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\OpportunityUserState;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Scores how well each user fits an opportunity (0–100 relevance) from their
 * expertise profile, and recommends a primary + secondary owner. Deterministic
 * and explainable (no AI / no external calls) so it's free, fast and runs daily
 * for the whole pipeline. Results are written to opportunity_user_states
 * (match_score / match_reasons / is_recommended / recommended_role) without
 * disturbing the user's own reaction on the same row.
 *
 * Relevance weights (applied to 0..1 component ratios):
 *   keywords 45 · product expertise 20 · industry expertise 15 · geography 10 · value band 10
 * The recommendation ranking additionally nudges by current workload so the
 * best-fit *and* available person floats to the top.
 */
class OpportunityMatchingService
{
    private const W_KEYWORDS = 0.45;
    private const W_PRODUCT = 0.20;
    private const W_INDUSTRY = 0.15;
    private const W_GEOGRAPHY = 0.10;
    private const W_VALUE = 0.10;

    /** Minimum relevance to be worth recommending / surfacing in the digest. */
    public const RECOMMEND_THRESHOLD = 35.0;

    /**
     * Score every candidate user for this opportunity, persist their state, and
     * flag the top primary + secondary recommendation. Returns the ranked
     * [user_id => score] map (highest first).
     *
     * @return array<int,float>
     */
    public function scoreOpportunity(Opportunity $opportunity): array
    {
        $candidates = $this->candidateUsers($opportunity->organization_id);
        if ($candidates->isEmpty()) {
            return [];
        }

        $blob = $this->opportunityBlob($opportunity);

        $ranked = $candidates->map(function (User $user) use ($opportunity, $blob) {
            $result = $this->scoreFor($opportunity, $user, $blob);

            return [
                'user' => $user,
                'score' => $result['score'],
                'reasons' => $result['reasons'],
                // Recommendation rank nudges best-fit toward less-loaded owners.
                'rank_score' => $result['score'] * (1 - min((int) ($user->workload_score ?? 0), 12) / 48),
            ];
        })
            ->sortByDesc('rank_score')
            ->values();

        // Top two above the threshold become primary / secondary recommendations.
        $primaryId = ($ranked[0]['score'] ?? 0) >= self::RECOMMEND_THRESHOLD ? $ranked[0]['user']->id : null;
        $secondaryId = ($ranked[1]['score'] ?? 0) >= self::RECOMMEND_THRESHOLD ? ($ranked[1]['user']->id ?? null) : null;

        $scores = [];
        foreach ($ranked as $row) {
            /** @var User $user */
            $user = $row['user'];
            $role = $user->id === $primaryId ? 'primary' : ($user->id === $secondaryId ? 'secondary' : null);

            $this->persist($opportunity, $user, $row['score'], $row['reasons'], $role);
            $scores[$user->id] = $row['score'];
        }

        return $scores;
    }

    /**
     * Compute a user's 0–100 relevance for an opportunity plus the reasons.
     * Pure (no DB writes). $blob may be passed to avoid rebuilding it per user.
     *
     * @return array{score:float,reasons:array<int,string>,components:array<string,float>}
     */
    public function scoreFor(Opportunity $opportunity, User $user, ?string $blob = null): array
    {
        $blob ??= $this->opportunityBlob($opportunity);
        $reasons = [];

        // Keywords — ratio of the user's keywords that appear in the notice.
        $keywords = $this->terms($user->pipeline_keywords);
        $kwMatched = $this->matched($keywords, $blob);
        $kw = $keywords === [] ? 0.0 : count($kwMatched) / count($keywords);
        if ($kwMatched !== []) {
            $reasons[] = 'Keywords: ' . implode(', ', array_slice($kwMatched, 0, 6));
        }

        // Product expertise.
        $products = $this->terms($user->product_expertise);
        $prodMatched = $this->matched($products, $blob);
        $prod = $products === [] ? 0.5 : count($prodMatched) / count($products);
        if ($prodMatched !== []) {
            $reasons[] = 'Product expertise: ' . implode(', ', array_slice($prodMatched, 0, 4));
        }

        // Industry expertise (checked against the notice + agency name).
        $industries = $this->terms($user->industry_expertise);
        $indMatched = $this->matched($industries, $blob . ' ' . mb_strtolower((string) $opportunity->agency_name));
        $ind = $industries === [] ? 0.5 : count($indMatched) / count($industries);
        if ($indMatched !== []) {
            $reasons[] = 'Industry: ' . implode(', ', array_slice($indMatched, 0, 4));
        }

        // Geography — full credit on a place-of-performance match.
        $geo = $this->geographyScore($opportunity, $user, $reasons);

        // Value band fit.
        $val = $this->valueScore($opportunity, $user, $reasons);

        $score = 100 * (
            self::W_KEYWORDS * $kw
            + self::W_PRODUCT * $prod
            + self::W_INDUSTRY * $ind
            + self::W_GEOGRAPHY * $geo
            + self::W_VALUE * $val
        );
        $score = round(max(0, min(100, $score)), 1);

        return [
            'score' => $score,
            'reasons' => $reasons,
            'components' => ['keywords' => $kw, 'product' => $prod, 'industry' => $ind, 'geography' => $geo, 'value' => $val],
        ];
    }

    /**
     * Opportunities recommended to a user (by score), newest-scored first, for
     * the daily digest. Only active, not-yet-closed opportunities.
     *
     * @return Collection<int,OpportunityUserState>
     */
    public function topForUser(User $user, int $limit = 10, float $minScore = self::RECOMMEND_THRESHOLD): Collection
    {
        return OpportunityUserState::where('user_id', $user->id)
            ->where('match_score', '>=', $minScore)
            ->whereHas('opportunity', fn ($q) => $q->active())
            ->with('opportunity:id,ulid,title,agency_name,due_date,estimated_value,currency,owner_id,assignment_stage')
            ->orderByDesc('match_score')
            ->limit($limit)
            ->get();
    }

    /** Active users in the org who can actually own/work an opportunity. */
    public function candidateUsers(int $organizationId): Collection
    {
        return User::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u) => $u->can('update opportunities') || $u->can('create proposals'))
            ->values();
    }

    /** Persist only the scoring fields, leaving the user's reaction intact. */
    private function persist(Opportunity $opportunity, User $user, float $score, array $reasons, ?string $role): void
    {
        $state = OpportunityUserState::firstOrNew([
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
        ]);
        $state->organization_id = $opportunity->organization_id;
        $state->match_score = $score;
        $state->match_reasons = $reasons === [] ? null : $reasons;
        $state->is_recommended = $role !== null;
        $state->recommended_role = $role;
        $state->save();
    }

    private function opportunityBlob(Opportunity $opportunity): string
    {
        return mb_strtolower(trim(implode(' ', array_filter([
            $opportunity->title,
            $opportunity->description,
            $opportunity->scope,
            $opportunity->requirements_summary,
            $opportunity->agency_name,
            $opportunity->sub_agency_name,
            $opportunity->naics_code,
            is_array($opportunity->matched_keywords) ? implode(' ', $opportunity->matched_keywords) : null,
        ]))));
    }

    private function geographyScore(Opportunity $opportunity, User $user, array &$reasons): float
    {
        $focus = $this->terms($user->geographic_focus);
        if ($focus === []) {
            return 0.5; // no stated preference — neutral
        }

        $place = mb_strtolower(trim(implode(' ', array_filter([
            $opportunity->place_of_performance_state,
            $opportunity->place_of_performance_country,
            $opportunity->place_of_performance_city,
        ]))));

        // "International" preference matches any non-US place; otherwise direct match.
        $focusLower = array_map('mb_strtolower', $focus);
        $isUs = $place === '' || str_contains($place, 'us') || str_contains($place, 'united states');
        if ($place === '') {
            return 0.5;
        }
        foreach ($focusLower as $f) {
            if ($f !== '' && str_contains($place, $f)) {
                $reasons[] = 'Geography: ' . $place;

                return 1.0;
            }
            if ($f === 'international' && ! $isUs) {
                return 1.0;
            }
            if (($f === 'united states' || $f === 'us') && $isUs) {
                return 1.0;
            }
        }

        return 0.4; // has a preference, this isn't it
    }

    private function valueScore(Opportunity $opportunity, User $user, array &$reasons): float
    {
        $value = $opportunity->estimated_value !== null ? (float) $opportunity->estimated_value : null;
        $min = $user->min_opportunity_value !== null ? (float) $user->min_opportunity_value : null;
        $max = $user->max_opportunity_value !== null ? (float) $user->max_opportunity_value : null;

        if ($value === null || ($min === null && $max === null)) {
            return 0.5; // unknown either side — neutral
        }
        if (($min === null || $value >= $min) && ($max === null || $value <= $max)) {
            $reasons[] = 'Within value band';

            return 1.0;
        }

        return 0.3; // outside the band the user pursues
    }

    /** @return array<int,string> */
    private function terms(mixed $list): array
    {
        if (! is_array($list)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($t) => trim((string) $t),
            $list,
        ), fn ($t) => mb_strlen($t) >= 2));
    }

    /**
     * Which of the given terms appear in the blob. Matching is word-boundary
     * aware (so "IT"/"AI" don't match inside "university"/"available") with light
     * singular/plural tolerance. Multi-word terms match on the full phrase or all
     * of their significant words.
     *
     * @param  array<int,string>  $terms
     * @return array<int,string>
     */
    private function matched(array $terms, string $blob): array
    {
        $hits = [];
        foreach ($terms as $term) {
            $needle = mb_strtolower(trim($term));

            if (str_contains($needle, ' ')) {
                $phrase = preg_quote($needle, '/');
                if (preg_match('/(?<![a-z0-9])' . $phrase . '(?![a-z0-9])/u', $blob)) {
                    $hits[] = $term;

                    continue;
                }
                $words = array_filter(preg_split('/[^a-z0-9]+/', $needle) ?: [], fn ($w) => mb_strlen($w) >= 4);
                if ($words !== [] && collect($words)->every(fn ($w) => $this->wordPresent($w, $blob))) {
                    $hits[] = $term;
                }

                continue;
            }

            if ($this->wordPresent($needle, $blob)) {
                $hits[] = $term;
            }
        }

        return array_values(array_unique($hits));
    }

    /** Boundary-aware word match with singular/plural tolerance. */
    private function wordPresent(string $word, string $blob): bool
    {
        $forms = [$word];
        if (mb_strlen($word) > 4 && str_ends_with($word, 's')) {
            $forms[] = rtrim($word, 's'); // tables → table
        } elseif (mb_strlen($word) >= 4) {
            $forms[] = $word . 's'; // table → tables
        }

        foreach ($forms as $form) {
            if (preg_match('/(?<![a-z0-9])' . preg_quote($form, '/') . '(?![a-z0-9])/u', $blob)) {
                return true;
            }
        }

        return false;
    }
}
