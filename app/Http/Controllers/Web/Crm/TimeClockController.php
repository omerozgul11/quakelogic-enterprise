<?php

namespace App\Http\Controllers\Web\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\TimeEntry;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TimeClockController extends Controller
{
    private const MANAGE_ALL = 'manage all time cards';

    // ---- Live clock widget (JSON) -----------------------------------------

    /** Current clock status for the signed-in user: open shift + today's total. */
    public function status(Request $request): JsonResponse
    {
        return response()->json($this->statusFor($request->user()));
    }

    public function clockIn(Request $request): JsonResponse
    {
        $user = $request->user();

        $open = $this->openEntryFor($user);
        if (! $open) {
            TimeEntry::create([
                'organization_id' => $user->organization_id,
                'user_id' => $user->id,
                'created_by' => $user->id,
                'clock_in' => now(),
                'source' => 'clock',
            ]);
        }

        return response()->json($this->statusFor($user));
    }

    public function clockOut(Request $request): JsonResponse
    {
        $user = $request->user();

        $open = $this->openEntryFor($user);
        if ($open) {
            $open->update(['clock_out' => now()]);
        }

        return response()->json($this->statusFor($user));
    }

    // ---- Time Cards page ---------------------------------------------------

    public function index(Request $request): Response
    {
        $user = $request->user();
        $manageAll = $user->can(self::MANAGE_ALL);

        // Default window: the current month through today.
        $from = $this->parseDate($request->query('from')) ?? now()->startOfMonth();
        $to = $this->parseDate($request->query('to')) ?? now();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        // Non-privileged users are locked to their own card.
        $filterUserId = $manageAll && $request->filled('user_id') ? (int) $request->query('user_id') : null;
        if (! $manageAll) {
            $filterUserId = $user->id;
        }

        $query = TimeEntry::forOrganization($user->organization_id)
            ->with('user:id,name')
            ->whereDate('clock_in', '>=', $from->toDateString())
            ->whereDate('clock_in', '<=', $to->toDateString())
            ->orderByDesc('clock_in');

        if ($filterUserId) {
            $query->where('user_id', $filterUserId);
        }

        $entries = $query->get();

        $rows = $entries->map(fn (TimeEntry $e) => [
            'id' => $e->id,
            'user_id' => $e->user_id,
            'user_name' => $e->user?->name ?? '—',
            'date' => $e->clock_in->format('M j, Y'),
            'weekday' => $e->clock_in->format('D'),
            'date_key' => $e->clock_in->toDateString(),
            'clock_in' => $e->clock_in->format('g:i A'),
            'clock_in_iso' => $e->clock_in->toIso8601String(),
            'clock_in_edit' => $e->clock_in->format('Y-m-d\TH:i'),
            'clock_out' => $e->clock_out?->format('g:i A'),
            'clock_out_edit' => $e->clock_out?->format('Y-m-d\TH:i'),
            'minutes' => $e->minutes(),
            'is_open' => $e->isOpen(),
            'note' => $e->note,
            'source' => $e->source,
            'can_edit' => $manageAll || $e->user_id === $user->id,
        ])->values();

        $totalMinutes = $entries->filter(fn (TimeEntry $e) => ! $e->isOpen())
            ->sum(fn (TimeEntry $e) => $e->minutes());
        $totalDays = $entries->pluck('clock_in')->map(fn ($d) => $d->toDateString())->unique()->count();

        return Inertia::render('Crm/TimeCards/Index', [
            'entries' => $rows,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'user_id' => $filterUserId,
            ],
            'summary' => [
                'total_minutes' => $totalMinutes,
                'total_days' => $totalDays,
                'entry_count' => $entries->count(),
                'open_count' => $entries->filter(fn (TimeEntry $e) => $e->isOpen())->count(),
            ],
            'users' => $manageAll
                ? User::where('organization_id', $user->organization_id)
                    ->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : [],
            'can' => [
                'manageAll' => $manageAll,
            ],
        ]);
    }

    // ---- Manual entries (add / correct) ------------------------------------

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validateEntry($request);

        $targetUserId = $this->resolveTargetUser($request, $user);

        TimeEntry::create([
            'organization_id' => $user->organization_id,
            'user_id' => $targetUserId,
            'created_by' => $user->id,
            'clock_in' => $data['clock_in'],
            'clock_out' => $data['clock_out'] ?? null,
            'note' => $data['note'] ?? null,
            'source' => 'manual',
        ]);

        return back()->with('success', 'Time entry added.');
    }

    public function update(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        $this->authorizeEntry($request->user(), $timeEntry);
        $data = $this->validateEntry($request);

        $timeEntry->update([
            'clock_in' => $data['clock_in'],
            'clock_out' => $data['clock_out'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Time entry updated.');
    }

    public function destroy(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        $this->authorizeEntry($request->user(), $timeEntry);
        $timeEntry->delete();

        return back()->with('success', 'Time entry removed.');
    }

    // ---- Helpers -----------------------------------------------------------

    private function statusFor(User $user): array
    {
        $open = $this->openEntryFor($user);

        // Completed minutes clocked today (the open shift ticks live on the client).
        $todayMinutes = TimeEntry::forOrganization($user->organization_id)
            ->where('user_id', $user->id)
            ->whereNotNull('clock_out')
            ->whereDate('clock_in', now()->toDateString())
            ->get()
            ->sum(fn (TimeEntry $e) => $e->minutes());

        return [
            'open' => $open ? [
                'id' => $open->id,
                'clock_in_iso' => $open->clock_in->toIso8601String(),
                'clock_in' => $open->clock_in->format('g:i A'),
            ] : null,
            'today_minutes' => $todayMinutes,
        ];
    }

    private function openEntryFor(User $user): ?TimeEntry
    {
        return TimeEntry::forOrganization($user->organization_id)
            ->where('user_id', $user->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();
    }

    /** @return array<string,mixed> */
    private function validateEntry(Request $request): array
    {
        return $request->validate([
            'clock_in' => ['required', 'date'],
            'clock_out' => ['nullable', 'date', 'after:clock_in'],
            'note' => ['nullable', 'string', 'max:500'],
            'user_id' => ['nullable', 'integer'],
        ]);
    }

    private function resolveTargetUser(Request $request, User $actor): int
    {
        if ($actor->can(self::MANAGE_ALL) && $request->filled('user_id')) {
            $candidate = (int) $request->input('user_id');
            $inOrg = User::where('organization_id', $actor->organization_id)->where('id', $candidate)->exists();
            if ($inOrg) {
                return $candidate;
            }
        }

        return $actor->id;
    }

    private function authorizeEntry(User $user, TimeEntry $entry): void
    {
        abort_unless(
            $entry->organization_id === $user->organization_id
            && ($entry->user_id === $user->id || $user->can(self::MANAGE_ALL)),
            403
        );
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Exception) {
            return null;
        }
    }
}
