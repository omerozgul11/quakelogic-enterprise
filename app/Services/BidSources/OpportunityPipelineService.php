<?php

namespace App\Services\BidSources;

use App\Models\Opportunity;
use App\Models\SamImport;
use App\Models\User;
use App\Services\BidSources\SamGov\SamGovImportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the opportunity pipeline fresh: removes opportunities whose deadline
 * has passed and pulls new ones from SAM.gov (throttled). Used by the on-login
 * refresh so the pipeline updates itself without a manual import.
 */
class OpportunityPipelineService
{
    /**
     * Statuses that represent active pursuit — never auto-removed even when the
     * deadline has passed (you may still be finishing or have already won it).
     */
    private const PROTECTED_STATUSES = [
        'pursuing', 'proposal_in_progress', 'submitted', 'under_evaluation', 'awarded',
    ];

    public function __construct(
        private readonly SamGovImportService $samImport,
        private readonly \App\Services\BidSources\SamGov\SamGovConnector $connector,
    ) {}

    /**
     * Soft-delete past-due opportunities that aren't being actively pursued and
     * have no application started against them. Returns the number removed.
     */
    public function purgeExpired(int $organizationId): int
    {
        $today = Carbon::now()->startOfDay()->toDateString();

        return Opportunity::query()
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', self::PROTECTED_STATUSES)
            ->whereDoesntHave('proposals')
            // Effective deadline is the response deadline, falling back to due date.
            // Rows with neither date set are left alone (NULL comparison is excluded).
            ->whereRaw('COALESCE(response_deadline, due_date) < ?', [$today])
            ->delete();
    }

    /**
     * Whether a SAM.gov pull is due (sync enabled and outside the throttle window).
     */
    public function shouldSync(int $organizationId): bool
    {
        if (!config('integrations.sam_gov.sync_enabled', false)) {
            return false;
        }

        $throttle = (int) config('pipeline.sync_throttle_minutes', 30);
        $last = SamImport::where('organization_id', $organizationId)
            ->latest('created_at')
            ->first();

        return $last === null || $last->created_at->lt(Carbon::now()->subMinutes($throttle));
    }

    /**
     * Pull fresh opportunities from SAM.gov and re-purge anything already expired.
     * Runs a broad recent pull, then one targeted pull per team keyword so
     * keyword filters always have matching contracts to surface — SAM's recency
     * feed alone rarely contains them. Never throws.
     */
    public function syncSamGov(User $user): array
    {
        try {
            $stats = $this->samImport->import($user->organization, [
                'max_pages' => (int) config('pipeline.sync_max_pages', 2),
            ], $user);

            foreach ($this->teamKeywords($user->organization_id) as $keyword) {
                $kwStats = $this->importKeyword($user, $keyword);
                $stats['imported'] += $kwStats['imported'] ?? 0;
                $stats['updated'] += $kwStats['updated'] ?? 0;
                $stats['errors'] += $kwStats['errors'] ?? 0;
            }

            $stats['purged'] = $this->purgeExpired($user->organization_id);

            return $stats;
        } catch (\Throwable $e) {
            Log::warning('Pipeline auto-sync failed', ['org' => $user->organization_id, 'error' => $e->getMessage()]);
            return ['imported' => 0, 'updated' => 0, 'errors' => 1, 'purged' => 0];
        }
    }

    /**
     * Targeted SAM.gov pull for a single keyword (matched against notice titles).
     * Looks back almost a year (keyword matches are sparse) but only asks for
     * notices whose response deadline is still open — recently-posted-but-expired
     * results would just be purged again right after import.
     */
    public function importKeyword(User $user, string $keyword): array
    {
        $stats = $this->samImport->import($user->organization, [
            'keyword' => $keyword,
            'max_pages' => 1,
            'postedFrom' => Carbon::now()->subMonths(11)->format('m/d/Y'),
            'rdlfrom' => Carbon::now()->format('m/d/Y'),
            'rdlto' => Carbon::now()->addMonths(11)->format('m/d/Y'),
        ], $user);

        // The official API matches keywords against titles only. Supplement with
        // SAM's full-text search so notices mentioning the keyword in the body
        // (the matches users see on sam.gov itself) land in the pipeline too.
        $fullText = $this->connector->searchFullText($keyword, 25);
        if ($fullText !== []) {
            $ftStats = $this->samImport->importResults($user->organization, $fullText, $user, [
                'keyword' => $keyword,
                'mode' => 'full_text',
            ]);
            foreach (['imported', 'updated', 'errors'] as $k) {
                $stats[$k] = ($stats[$k] ?? 0) + ($ftStats[$k] ?? 0);
            }

            // Tag the imported notices with the keyword that found them: a
            // full-text match may not appear in any column we store, and the
            // keyword filter chips search matched_keywords as a fallback.
            $externalIds = array_map(fn ($dto) => $dto->externalId, $fullText);
            Opportunity::where('organization_id', $user->organization_id)
                ->where('source', 'sam_gov')
                ->whereIn('external_id', $externalIds)
                ->get(['id', 'matched_keywords'])
                ->each(function (Opportunity $o) use ($keyword) {
                    $tags = $o->matched_keywords ?? [];
                    if (!in_array(mb_strtolower($keyword), array_map('mb_strtolower', $tags), true)) {
                        $tags[] = $keyword;
                        $o->update(['matched_keywords' => array_values($tags)]);
                    }
                });
        }

        return $stats;
    }

    /**
     * Every personal keyword saved by any active user in the organization.
     * Keywords stay private in the UI; they steer which public SAM.gov notices
     * get imported into the shared pipeline and which awards the market-pricing
     * feed benchmarks against.
     */
    public function teamKeywords(int $organizationId): array
    {
        $keywords = User::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereNotNull('pipeline_keywords')
            ->pluck('pipeline_keywords')
            ->flatten()
            ->filter(fn ($k) => is_string($k) && trim($k) !== '')
            ->unique(fn ($k) => mb_strtolower(trim($k)))
            ->values();

        return $keywords->take((int) config('pipeline.keyword_sync_max', 8))->all();
    }
}
