<?php

namespace App\Services\Opportunities;

use App\Enums\OpportunityPriority;
use App\Models\Opportunity;
use App\Models\OpportunityKeywordGroup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Scores an opportunity for QuakeLogic relevance using the org's admin-editable
 * keyword groups, NAICS fit, due-date urgency and estimated value, with
 * exclusion groups forcing "Not Relevant". Produces a 0–100 score, a priority,
 * the matched keywords, and an explainable breakdown.
 */
class OpportunityScorer
{
    private const TITLE_HIT = 16;
    private const BODY_HIT = 8;
    private const GROUP_CAP = 48;
    private const KEYWORD_CAP = 70;
    private const NAICS_BOOST = 20;
    private const VALUE_BOOST = 5;
    private const VALUE_THRESHOLD = 250000;
    private const HIGH = 35;
    private const MEDIUM = 15;

    /** @var array<int,Collection<int,OpportunityKeywordGroup>> */
    private array $groupCache = [];

    public function scoreAndStore(Opportunity $opportunity): Opportunity
    {
        $result = $this->score($opportunity);

        // Quiet save: don't re-fire Auditable / Scout / project observers.
        $opportunity->forceFill([
            'relevance_score' => $result['score'],
            'priority' => $result['priority'],
            'matched_keywords' => $result['matched'],
            'score_breakdown' => $result['breakdown'],
        ])->saveQuietly();

        return $opportunity;
    }

    /**
     * @return array{score:int,priority:string,matched:array<int,string>,breakdown:array<string,mixed>}
     */
    public function score(Opportunity $opportunity): array
    {
        $groups = $this->groupsFor((int) $opportunity->organization_id);

        $title = Str::lower((string) $opportunity->title);
        $body = Str::lower(implode(' ', array_filter([
            $opportunity->description, $opportunity->scope, $opportunity->requirements_summary,
            $opportunity->agency_name, $opportunity->sub_agency_name,
        ])));
        $naics = trim((string) $opportunity->naics_code);

        $matched = [];
        $excludedBy = [];
        $groupScores = [];
        $keywordScore = 0.0;

        foreach ($groups as $group) {
            $titleHits = 0;
            $bodyHits = 0;
            $groupMatched = [];
            foreach ((array) $group->keywords as $keyword) {
                $k = Str::lower(trim((string) $keyword));
                if ($k === '') {
                    continue;
                }
                if (str_contains($title, $k)) {
                    $titleHits++;
                    $groupMatched[] = $keyword;
                } elseif (str_contains($body, $k)) {
                    $bodyHits++;
                    $groupMatched[] = $keyword;
                }
            }

            if ($group->is_exclusion) {
                if ($groupMatched) {
                    $excludedBy[] = $group->name;
                }

                continue;
            }
            if (! $groupMatched) {
                continue;
            }

            $weightFactor = max(1, (int) $group->weight) / 10;
            $raw = min(self::GROUP_CAP, $titleHits * self::TITLE_HIT + $bodyHits * self::BODY_HIT) * $weightFactor;
            $keywordScore += $raw;
            $matched = array_merge($matched, $groupMatched);
            $groupScores[$group->name] = round($raw, 1);
        }
        $keywordScore = min(self::KEYWORD_CAP, $keywordScore);

        $naicsScore = 0;
        if ($naics !== '') {
            foreach ($groups as $group) {
                if (! $group->is_exclusion && in_array($naics, array_map('strval', (array) $group->naics_codes), true)) {
                    $naicsScore = self::NAICS_BOOST;
                    break;
                }
            }
        }

        $urgency = 0;
        $due = $opportunity->due_date ?? $opportunity->response_deadline;
        if ($due instanceof \DateTimeInterface) {
            $days = (int) Carbon::now()->startOfDay()->diffInDays(Carbon::parse($due), false);
            $urgency = $days < 0 ? 0 : ($days <= 7 ? 10 : ($days <= 14 ? 6 : ($days <= 30 ? 3 : 0)));
        }

        $valueScore = ($opportunity->estimated_value !== null && (float) $opportunity->estimated_value >= self::VALUE_THRESHOLD)
            ? self::VALUE_BOOST : 0;

        $matched = array_values(array_unique($matched));
        $excluded = $excludedBy !== [];
        $score = (int) round(min(100, $keywordScore + $naicsScore + $urgency + $valueScore));

        $priority = match (true) {
            $excluded => OpportunityPriority::NotRelevant,
            empty($matched) && $naicsScore === 0 => OpportunityPriority::NotRelevant,
            $score >= self::HIGH => OpportunityPriority::High,
            $score >= self::MEDIUM => OpportunityPriority::Medium,
            $score > 0 => OpportunityPriority::Low,
            default => OpportunityPriority::NotRelevant,
        };
        if ($excluded) {
            $score = 0;
        }

        return [
            'score' => $score,
            'priority' => $priority->value,
            'matched' => $matched,
            'breakdown' => [
                'keyword' => round($keywordScore, 1),
                'naics' => $naicsScore,
                'urgency' => $urgency,
                'value' => $valueScore,
                'groups' => $groupScores,
                'excluded_by' => $excludedBy,
            ],
        ];
    }

    /** @return Collection<int,OpportunityKeywordGroup> */
    private function groupsFor(int $organizationId): Collection
    {
        return $this->groupCache[$organizationId] ??= OpportunityKeywordGroup::forOrganization($organizationId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function clearCache(): void
    {
        $this->groupCache = [];
    }
}
