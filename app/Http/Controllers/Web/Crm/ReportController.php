<?php

namespace App\Http\Controllers\Web\Crm;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Crm\Activity;
use App\Models\Crm\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only sales analytics over the leads pipeline: funnel, win rate,
 * conversion by source/owner, weighted forecast and sales velocity. No writes,
 * no schema — pure aggregation, org-scoped.
 */
class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Lead::class);
        $orgId = $request->user()->organization_id;

        // Period filter on lead creation: 30 / 90 / 365 days, or all-time.
        $period = (int) $request->query('period', 90);
        $period = in_array($period, [30, 90, 365, 0], true) ? $period : 90;

        $leads = Lead::where('organization_id', $orgId)
            ->when($period > 0, fn ($q) => $q->where('created_at', '>=', Carbon::now()->subDays($period)))
            ->get(['id', 'status', 'source', 'owner_id', 'estimated_value', 'probability', 'expected_close_date', 'created_at', 'updated_at']);

        $owners = Lead::where('organization_id', $orgId)->whereNotNull('owner_id')
            ->with('owner:id,name')->get(['owner_id'])->pluck('owner.name', 'owner_id');

        return Inertia::render('Crm/Reports/Index', [
            'period' => $period,
            'funnel' => $this->funnel($leads),
            'summary' => $this->summary($leads),
            'bySource' => $this->bySource($leads),
            'byOwner' => $this->byOwner($leads, $owners),
            'forecast' => $this->forecast($leads),
            'velocity' => $this->velocity($orgId, $leads),
        ]);
    }

    private function won(Collection $leads): Collection
    {
        return $leads->where('status', LeadStatus::Won);
    }

    private function lost(Collection $leads): Collection
    {
        return $leads->where('status', LeadStatus::Lost);
    }

    private function open(Collection $leads): Collection
    {
        return $leads->filter(fn (Lead $l) => $l->status->isOpen());
    }

    private function rate(int $won, int $lost): float
    {
        $decided = $won + $lost;

        return $decided > 0 ? round($won / $decided * 100, 1) : 0.0;
    }

    /** @return array<int, array<string,mixed>> */
    private function funnel(Collection $leads): array
    {
        return collect(LeadStatus::pipeline())->map(fn (LeadStatus $s) => [
            'key' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
            'count' => $leads->where('status', $s)->count(),
            'value' => (float) $leads->where('status', $s)->sum('estimated_value'),
        ])->values()->all();
    }

    /** @return array<string,mixed> */
    private function summary(Collection $leads): array
    {
        $won = $this->won($leads);
        $lost = $this->lost($leads);
        $open = $this->open($leads);

        return [
            'total' => $leads->count(),
            'won' => $won->count(),
            'lost' => $lost->count(),
            'open' => $open->count(),
            'win_rate' => $this->rate($won->count(), $lost->count()),
            'won_value' => (float) $won->sum('estimated_value'),
            'open_value' => (float) $open->sum('estimated_value'),
            'avg_deal' => $won->count() ? round((float) $won->sum('estimated_value') / $won->count(), 2) : 0.0,
        ];
    }

    /** @return array<int, array<string,mixed>> */
    private function bySource(Collection $leads): array
    {
        return $leads->groupBy(fn (Lead $l) => $l->source ?: 'Unknown')
            ->map(function (Collection $group, string $source) {
                $won = $group->where('status', LeadStatus::Won)->count();
                $lost = $group->where('status', LeadStatus::Lost)->count();

                return [
                    'source' => $source,
                    'total' => $group->count(),
                    'won' => $won,
                    'open' => $group->filter(fn (Lead $l) => $l->status->isOpen())->count(),
                    'value' => (float) $group->sum('estimated_value'),
                    'win_rate' => $this->rate($won, $lost),
                ];
            })
            ->sortByDesc('total')->values()->all();
    }

    /** @return array<int, array<string,mixed>> */
    private function byOwner(Collection $leads, Collection $owners): array
    {
        return $leads->groupBy('owner_id')
            ->map(function (Collection $group, $ownerId) use ($owners) {
                $won = $group->where('status', LeadStatus::Won)->count();
                $lost = $group->where('status', LeadStatus::Lost)->count();

                return [
                    'owner' => $owners[$ownerId] ?? 'Unassigned',
                    'total' => $group->count(),
                    'won' => $won,
                    'open' => $group->filter(fn (Lead $l) => $l->status->isOpen())->count(),
                    'value' => (float) $group->sum('estimated_value'),
                    'win_rate' => $this->rate($won, $lost),
                ];
            })
            ->sortByDesc('total')->values()->all();
    }

    /**
     * Weighted pipeline (open value × probability) overall and by expected-close
     * month for the next six months.
     *
     * @return array<string,mixed>
     */
    private function forecast(Collection $leads): array
    {
        $open = $this->open($leads);

        $weighted = (float) $open->sum(fn (Lead $l) => (float) $l->estimated_value * (($l->probability ?? 0) / 100));

        $months = collect(range(0, 5))->map(function (int $i) {
            $start = Carbon::now()->startOfMonth()->addMonths($i);

            return ['key' => $start->format('Y-m'), 'label' => $start->format('M Y'), 'weighted' => 0.0, 'count' => 0];
        })->keyBy('key');

        foreach ($open as $lead) {
            if (! $lead->expected_close_date) {
                continue;
            }
            $key = $lead->expected_close_date->format('Y-m');
            if ($months->has($key)) {
                $m = $months->get($key);
                $m['weighted'] += (float) $lead->estimated_value * (($lead->probability ?? 0) / 100);
                $m['count']++;
                $months->put($key, $m);
            }
        }

        return [
            'weighted_total' => round($weighted, 2),
            'unweighted_total' => (float) $open->sum('estimated_value'),
            'by_month' => $months->values()->all(),
        ];
    }

    /**
     * Average days from lead creation to win. Prefers the logged "won" activity
     * timestamp; falls back to the lead's updated_at for leads won before the
     * timeline existed.
     *
     * @return array<string,mixed>
     */
    private function velocity(int $orgId, Collection $leads): array
    {
        $wonLeads = $this->won($leads);
        if ($wonLeads->isEmpty()) {
            return ['avg_days' => null, 'sample' => 0];
        }

        $wonIds = $wonLeads->pluck('id')->all();

        // Latest won/converted activity timestamp per won lead.
        $wonAt = Activity::forOrganization($orgId)
            ->where('subject_type', (new Lead)->getMorphClass())
            ->whereIn('subject_id', $wonIds)
            ->whereIn('type', ['stage_change', 'converted'])
            ->get(['subject_id', 'type', 'meta', 'happened_at'])
            ->filter(fn (Activity $a) => $a->type === 'converted' || ($a->meta['to'] ?? null) === LeadStatus::Won->value)
            ->groupBy('subject_id')
            ->map(fn (Collection $g) => $g->max('happened_at'));

        $days = $wonLeads->map(function (Lead $l) use ($wonAt) {
            $end = $wonAt->get($l->id) ?? $l->updated_at;
            if (! $l->created_at || ! $end) {
                return null;
            }

            return max(0, $l->created_at->diffInDays($end));
        })->filter(fn ($d) => $d !== null);

        return [
            'avg_days' => $days->isNotEmpty() ? round($days->avg(), 1) : null,
            'sample' => $days->count(),
        ];
    }
}
