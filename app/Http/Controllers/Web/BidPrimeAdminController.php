<?php

namespace App\Http\Controllers\Web;

use App\Enums\OpportunityPriority;
use App\Http\Controllers\Controller;
use App\Models\BidprimeEmail;
use App\Models\BidprimeImport;
use App\Models\Opportunity;
use App\Services\BidSources\BidPrime\GmailBidPrimeIngestService;
use App\Services\Email\Gmail\GmailInboxClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin review dashboard for the BidPrime email pipeline: imported emails,
 * parsed opportunities by priority, duplicates, failed parses, import logs, and
 * reprocess / approve / reject controls. Gated by the /admin route group
 * (role:Super Admin).
 */
class BidPrimeAdminController extends Controller
{
    public function __construct(
        private readonly GmailBidPrimeIngestService $ingest,
        private readonly GmailInboxClient $inbox,
    ) {}

    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;
        $priority = $request->string('priority')->toString();

        $bidprime = fn () => Opportunity::where('organization_id', $orgId)->where('source', 'bidprime');

        $opportunities = $bidprime()
            ->when($priority !== '', fn ($q) => $q->where('priority', $priority))
            ->orderByDesc('relevance_score')->orderByDesc('id')
            ->paginate(20)->withQueryString()
            ->through(fn (Opportunity $o) => $this->opportunityRow($o));

        return Inertia::render('Admin/BidPrime/Dashboard', [
            'stats' => $this->stats($orgId),
            'emails' => BidprimeEmail::where('organization_id', $orgId)->latest('received_at')->latest('id')->limit(25)->get()
                ->map(fn (BidprimeEmail $e) => $this->emailRow($e))->values(),
            'imports' => BidprimeImport::where('organization_id', $orgId)->latest('id')->limit(10)->get()
                ->map(fn (BidprimeImport $i) => $this->importRow($i))->values(),
            'opportunities' => $opportunities,
            'priorities' => OpportunityPriority::options(),
            'filters' => ['priority' => $priority],
            'inboxConfigured' => $this->inbox->isConfigured(),
        ]);
    }

    public function showEmail(Request $request, BidprimeEmail $email): Response
    {
        abort_unless($email->organization_id === $request->user()->organization_id, 404);
        $email->load(['items.opportunity']);

        return Inertia::render('Admin/BidPrime/EmailShow', [
            'email' => [
                ...$this->emailRow($email),
                'thread_id' => $email->thread_id,
                'gmail_message_id' => $email->gmail_message_id,
                'raw_html' => $email->raw_html,
                'raw_text' => $email->raw_text,
                'processed_at' => $email->processed_at?->toIso8601String(),
            ],
            'opportunities' => $email->items->map(function ($item) {
                $row = $item->opportunity ? $this->opportunityRow($item->opportunity) : null;

                return [
                    'item_id' => $item->id,
                    'action' => $item->action,
                    'external_id' => $item->external_id,
                    'title' => $item->title,
                    'opportunity' => $row,
                ];
            })->values(),
        ]);
    }

    public function importNow(Request $request): RedirectResponse
    {
        $import = $this->ingest->ingest();

        return back()->with('success', sprintf(
            'Import #%d: %d created, %d updated, %d duplicate, %d errors.',
            $import->id, $import->total_created, $import->total_updated, $import->total_skipped, $import->total_errors,
        ));
    }

    public function reprocessRecent(Request $request): RedirectResponse
    {
        $import = $this->ingest->ingest(['since_days' => 7, 'reprocess' => true]);

        return back()->with('success', "Reprocessed the last 7 days (import #{$import->id}).");
    }

    public function reprocessFailed(Request $request): RedirectResponse
    {
        $orgId = $request->user()->organization_id;
        $failed = BidprimeEmail::where('organization_id', $orgId)->where('status', 'failed')->get();
        foreach ($failed as $email) {
            $this->ingest->reprocessEmail($email);
        }

        return back()->with('success', "Reprocessed {$failed->count()} failed email(s).");
    }

    public function reprocessEmail(Request $request, BidprimeEmail $email): RedirectResponse
    {
        abort_unless($email->organization_id === $request->user()->organization_id, 404);
        $this->ingest->reprocessEmail($email);

        return back()->with('success', 'Email reprocessed.');
    }

    public function approveOpportunity(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->reviewable($request, $opportunity);
        $opportunity->update(['status' => 'qualified']);

        return back()->with('success', 'Opportunity approved (qualified).');
    }

    public function rejectOpportunity(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->reviewable($request, $opportunity);
        $opportunity->update(['status' => 'no_bid']);

        return back()->with('success', 'Opportunity rejected (no-bid).');
    }

    private function reviewable(Request $request, Opportunity $opportunity): void
    {
        abort_unless(
            $opportunity->organization_id === $request->user()->organization_id && $opportunity->source?->value === 'bidprime',
            404,
        );
    }

    /** @return array<string,mixed> */
    private function stats(int $orgId): array
    {
        $emailCounts = BidprimeEmail::where('organization_id', $orgId)
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        $priorityCounts = Opportunity::where('organization_id', $orgId)->where('source', 'bidprime')
            ->selectRaw('priority, count(*) as c')->groupBy('priority')->pluck('c', 'priority');

        return [
            'emails_total' => (int) $emailCounts->sum(),
            'emails_parsed' => (int) ($emailCounts['parsed'] ?? 0),
            'emails_failed' => (int) ($emailCounts['failed'] ?? 0),
            'emails_no_opps' => (int) ($emailCounts['no_opportunities'] ?? 0),
            'opps_total' => Opportunity::where('organization_id', $orgId)->where('source', 'bidprime')->count(),
            'priority' => [
                'high' => (int) ($priorityCounts['high'] ?? 0),
                'medium' => (int) ($priorityCounts['medium'] ?? 0),
                'low' => (int) ($priorityCounts['low'] ?? 0),
                'not_relevant' => (int) ($priorityCounts['not_relevant'] ?? 0),
            ],
            'duplicates' => Opportunity::where('organization_id', $orgId)->where('source', 'bidprime')->where('is_duplicate_flagged', true)->count(),
        ];
    }

    /** @return array<string,mixed> */
    private function emailRow(BidprimeEmail $e): array
    {
        return [
            'id' => $e->id,
            'subject' => $e->subject,
            'from' => $e->from_name ?: $e->from_email,
            'received_at' => $e->received_at?->toIso8601String(),
            'status' => $e->status,
            'opportunities_found' => $e->opportunities_found,
            'parse_error' => $e->parse_error,
        ];
    }

    /** @return array<string,mixed> */
    private function importRow(BidprimeImport $i): array
    {
        return [
            'id' => $i->id,
            'status' => $i->status,
            'channel' => $i->filters['channel'] ?? 'api',
            'created' => $i->total_created,
            'updated' => $i->total_updated,
            'skipped' => $i->total_skipped,
            'errors' => $i->total_errors,
            'started_at' => $i->started_at?->toIso8601String(),
            'completed_at' => $i->completed_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private function opportunityRow(Opportunity $o): array
    {
        $priority = $o->priority instanceof OpportunityPriority ? $o->priority : null;

        return [
            'id' => $o->id,
            'title' => $o->title,
            'agency_name' => $o->agency_name,
            'solicitation_number' => $o->solicitation_number,
            'naics_code' => $o->naics_code,
            'due_date' => $o->due_date?->toDateString(),
            'estimated_value' => $o->estimated_value !== null ? (float) $o->estimated_value : null,
            'status' => $o->status?->value,
            'priority' => $priority?->value,
            'priority_label' => $priority?->label(),
            'priority_color' => $priority?->color(),
            'relevance_score' => $o->relevance_score,
            'matched_keywords' => $o->matched_keywords ?? [],
            'score_breakdown' => $o->score_breakdown,
            'is_duplicate_flagged' => (bool) $o->is_duplicate_flagged,
            'bidprime_url' => $o->raw_source_data['bidprime_url'] ?? $o->source_url,
            'source_url' => $o->source_url,
        ];
    }
}
