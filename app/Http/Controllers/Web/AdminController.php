<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Support\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AdminController extends Controller implements HasMiddleware
{
    /**
     * Defense-in-depth: every admin action (user management + team activity)
     * requires the Super Admin role, regardless of how the route is registered.
     */
    public static function middleware(): array
    {
        return ['role:Super Admin'];
    }

    public function index(Request $request): Response
    {
        $users = User::where('organization_id', $request->user()->organization_id)
            ->with('roles')
            ->orderBy('name')
            ->paginate(25);

        $roles = Role::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function users(Request $request): Response
    {
        return $this->index($request);
    }

    /**
     * Team activity dashboard: what each employee owns, is working on, and has delivered.
     */
    public function team(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $active = ['draft', 'in_progress', 'under_review'];
        $submitted = ['submitted', 'pending', 'clarification_requested', 'negotiation', 'awarded', 'completed', 'lost'];
        $won = \App\Enums\ProposalStatus::wonValues();

        $users = User::where('organization_id', $orgId)
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'title', 'is_active']);

        // Aggregate proposals by owner + status in one query. Money is
        // normalised to USD since proposals may be in any currency. The status is
        // flattened to its raw string value here — keeping it as a cast enum
        // would break the whereIn()/where() collection lookups below.
        $proposalRows = ProposalSubmission::where('organization_id', $orgId)
            ->selectRaw('owner_id, status, COUNT(*) as c, COALESCE(SUM(' . Currency::usdExpr('proposal_value') . '), 0) as v, COALESCE(SUM(' . Currency::usdExpr('award_value') . '), 0) as aw')
            ->groupBy('owner_id', 'status')
            ->get()
            ->map(fn ($r) => [
                'owner_id' => $r->owner_id,
                'status' => $r->status instanceof \BackedEnum ? $r->status->value : $r->status,
                'c' => (int) $r->c,
                'v' => (float) $r->v,
                'aw' => (float) $r->aw,
            ]);

        $oppRows = Opportunity::where('organization_id', $orgId)
            ->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, COUNT(*) as c')
            ->groupBy('assigned_to')
            ->pluck('c', 'assigned_to');

        $followRows = FollowUp::where('organization_id', $orgId)
            ->whereIn('status', ['scheduled', 'overdue'])
            ->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, COUNT(*) as c')
            ->groupBy('assigned_to')
            ->pluck('c', 'assigned_to');

        $recent = ProposalSubmission::where('organization_id', $orgId)
            ->with('owner:id,name')
            ->latest('updated_at')
            ->limit(10)
            ->get(['id', 'proposal_number', 'project_name', 'status', 'owner_id', 'proposal_value', 'currency', 'updated_at'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'proposal_number' => $p->proposal_number,
                'project_name' => $p->project_name,
                'status' => $p->status instanceof \BackedEnum ? $p->status->value : $p->status,
                'owner' => $p->owner?->name,
                'value' => Currency::toUsd((float) $p->proposal_value, $p->currency),
                'updated_at' => $p->updated_at?->toIso8601String(),
            ]);

        $team = $users->map(function ($u) use ($proposalRows, $oppRows, $followRows, $active, $submitted, $won) {
            $rows = $proposalRows->where('owner_id', $u->id);

            $bucket = fn (array $statuses) => (int) $rows->whereIn('status', $statuses)->sum('c');

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'title' => $u->title,
                'role' => $u->roles->first()?->name,
                'is_active' => $u->is_active,
                'proposals_total' => (int) $rows->sum('c'),
                'proposals_active' => $bucket($active),
                'proposals_submitted' => $bucket($submitted),
                'proposals_won' => $bucket($won),
                'pipeline_value' => (float) $rows->whereIn('status', $active)->sum('v'),
                'won_value' => (float) $rows->whereIn('status', $won)->sum('aw'),
                'opportunities' => (int) ($oppRows[$u->id] ?? 0),
                'open_follow_ups' => (int) ($followRows[$u->id] ?? 0),
            ];
        })->values();

        // Total proposals split into each status as its own category.
        $statusBreakdown = collect(\App\Enums\ProposalStatus::cases())->map(fn ($s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
            'count' => (int) $proposalRows->where('status', $s->value)->sum('c'),
        ])->values();

        return Inertia::render('Admin/Team', [
            'team' => $team,
            'recent' => $recent,
            'statusBreakdown' => $statusBreakdown,
            'totals' => [
                'employees' => $users->count(),
                'proposals' => (int) $proposalRows->sum('c'),
                'pipeline_value' => (float) $proposalRows->whereIn('status', $active)->sum('v'),
                'won' => (int) $proposalRows->whereIn('status', $won)->sum('c'),
            ],
        ]);
    }

    /**
     * Per-user activity: who did what within the selected period (day / week /
     * month / year). Aggregated from real records since audit logging is off.
     */
    public function activity(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        // Custom from/to range takes precedence over the preset periods.
        $parse = function (?string $value) {
            try {
                return $value ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $value) : null;
            } catch (\Throwable) {
                return null;
            }
        };
        $from = $parse($request->input('from'));
        $to = $parse($request->input('to'));

        if ($from && $to) {
            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }
            $period = 'custom';
            $start = $from->copy()->startOfDay();
            $end = $to->copy()->endOfDay();
        } else {
            $from = $to = null;
            $end = null;
            $period = in_array($request->input('period'), ['day', 'week', 'month', 'year'], true)
                ? $request->input('period') : 'month';
            $start = match ($period) {
                'day' => now()->startOfDay(),
                'week' => now()->startOfWeek(),
                'year' => now()->startOfYear(),
                default => now()->startOfMonth(),
            };
        }

        $by = fn ($query, string $col, string $agg = 'count(*)') => $query->where('organization_id', $orgId)
            ->whereNotNull($col)
            ->select($col, DB::raw("{$agg} as c"))
            ->groupBy($col)
            ->pluck('c', $col)
            ->toArray();

        $range = fn ($query, string $col) => $query
            ->where($col, '>=', $start)
            ->when($end, fn ($q) => $q->where($col, '<=', $end));

        $proposalsCreated = $by($range(ProposalSubmission::query(), 'created_at'), 'created_by');
        $proposalsSubmitted = $by($range(ProposalSubmission::whereNotNull('submission_date'), 'submission_date'), 'owner_id');
        $followups = $by($range(FollowUp::query(), 'created_at'), 'created_by');

        // Same activity, but in dollars: value of proposals created / submitted /
        // awarded in the period, per user.
        $valueCreated = $by($range(ProposalSubmission::query(), 'created_at'), 'created_by', 'COALESCE(SUM(' . Currency::usdExpr('proposal_value') . '), 0)');
        $valueSubmitted = $by($range(ProposalSubmission::whereNotNull('submission_date'), 'submission_date'), 'owner_id', 'COALESCE(SUM(' . Currency::usdExpr('proposal_value') . '), 0)');
        $valueAwarded = $by(
            $range(ProposalSubmission::whereIn('status', \App\Enums\ProposalStatus::wonValues())->whereNotNull('award_date'), 'award_date'),
            'owner_id',
            'COALESCE(SUM(' . Currency::usdExpr('COALESCE(NULLIF(award_value, 0), proposal_value)') . '), 0)'
        );

        $team = User::where('organization_id', $orgId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($u) use ($proposalsCreated, $proposalsSubmitted, $followups, $valueCreated, $valueSubmitted, $valueAwarded) {
                $row = [
                    'user' => $u->name,
                    'proposals' => (int) ($proposalsCreated[$u->id] ?? 0),
                    'submitted' => (int) ($proposalsSubmitted[$u->id] ?? 0),
                    'followups' => (int) ($followups[$u->id] ?? 0),
                    'value_created' => (float) ($valueCreated[$u->id] ?? 0),
                    'value_submitted' => (float) ($valueSubmitted[$u->id] ?? 0),
                    'value_awarded' => (float) ($valueAwarded[$u->id] ?? 0),
                ];
                $row['total'] = $row['proposals'] + $row['submitted'] + $row['followups'];
                return $row;
            })
            ->sortByDesc('total')
            ->values();

        return Inertia::render('Admin/Activity', [
            'team' => $team,
            'period' => $period,
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'totals' => [
                'proposals' => array_sum($proposalsCreated),
                'submitted' => array_sum($proposalsSubmitted),
                'followups' => array_sum($followups),
                'value_created' => (float) array_sum($valueCreated),
                'value_submitted' => (float) array_sum($valueSubmitted),
                'value_awarded' => (float) array_sum($valueAwarded),
            ],
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|string',
            'custom_role' => 'nullable|string|max:255|required_if:role,__custom__',
            'base_role' => 'nullable|string|exists:roles,name',
        ]);

        $roleName = $this->resolveRoleName($validated);

        $user = User::create([
            'ulid' => (string) Str::ulid(),
            'organization_id' => $request->user()->organization_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);
        $user->syncRoles([$roleName]);

        return back()->with('success', 'User created.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'role' => 'required|string',
            'custom_role' => 'nullable|string|max:255|required_if:role,__custom__',
            'base_role' => 'nullable|string|exists:roles,name',
        ]);

        $roleName = $this->resolveRoleName($validated);

        $user->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);
        $user->syncRoles([$roleName]);

        return back()->with('success', 'User updated.');
    }

    /**
     * Resolve the role name to assign. When the admin chose "Create custom
     * role", a new role is created (or reused if the name already exists) and
     * seeded with the permissions of the chosen base role so it is functional
     * from the start. Otherwise the selected existing role name is returned.
     *
     * @param  array<string,mixed>  $data
     */
    private function resolveRoleName(array $data): string
    {
        $role = trim((string) ($data['role'] ?? ''));

        if ($role !== '__custom__') {
            if (!Role::where('name', $role)->where('guard_name', 'web')->exists()) {
                throw ValidationException::withMessages(['role' => 'Please choose a valid role.']);
            }
            return $role;
        }

        $name = trim((string) ($data['custom_role'] ?? ''));
        if ($name === '' || strcasecmp($name, '__custom__') === 0) {
            throw ValidationException::withMessages(['custom_role' => 'Enter a name for the custom role.']);
        }

        $newRole = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

        // Seed permissions from the base role, but only for a freshly created
        // role — never overwrite the permissions of one that already existed.
        if ($newRole->wasRecentlyCreated && !empty($data['base_role'])) {
            $base = Role::where('name', $data['base_role'])->where('guard_name', 'web')->with('permissions')->first();
            if ($base) {
                $newRole->syncPermissions($base->permissions);
            }
        }

        return $newRole->name;
    }

    public function deleteUser(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 403);
        abort_if($user->id === $request->user()->id, 403, 'You cannot delete your own account.');

        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    public function auditLogs(Request $request): Response
    {
        $logs = AuditLog::where('organization_id', $request->user()->organization_id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(50);

        return Inertia::render('Admin/AuditLogs', ['logs' => $logs]);
    }
}
