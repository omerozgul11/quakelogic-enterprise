<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Services\Mail\MailboxConnectionService;
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

    public function index(Request $request, MailboxConnectionService $mailboxes): Response
    {
        $users = User::where('organization_id', $request->user()->organization_id)
            ->with('roles', 'emailAccount')
            ->orderBy('name')
            ->paginate(25)
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_active' => $u->is_active,
                'title' => $u->title,
                'phone' => $u->phone,
                'department' => $u->department,
                'pipeline_keywords' => $u->pipeline_keywords ?? [],
                'product_expertise' => $u->product_expertise ?? [],
                'industry_expertise' => $u->industry_expertise ?? [],
                'geographic_focus' => $u->geographic_focus ?? [],
                'min_opportunity_value' => $u->min_opportunity_value,
                'max_opportunity_value' => $u->max_opportunity_value,
                'workload_score' => $u->workload_score,
                'created_at' => $u->created_at?->toIso8601String(),
                'roles' => $u->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values(),
                'mailbox' => $mailboxes->state($u->emailAccount),
            ]);

        $roles = Role::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function users(Request $request, MailboxConnectionService $mailboxes): Response
    {
        return $this->index($request, $mailboxes);
    }

    /**
     * Team activity dashboard: what each employee owns, is working on, and has delivered.
     */
    public function team(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $active = ['in_progress'];
        $submitted = ['submitted', 'award_pending', 'awarded', 'completed', 'lost'];
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

        // Workload distribution: proposals a user was *given* (owns but someone
        // else created — i.e. an admin assigned it) vs. *picked up* (created and
        // owns themselves). Counted by the owner over the period.
        $pickedUp = $by($range(ProposalSubmission::whereColumn('created_by', 'owner_id'), 'created_at'), 'owner_id');
        $assigned = $by(
            $range(ProposalSubmission::query()->where(fn ($q) => $q->whereColumn('created_by', '!=', 'owner_id')->orWhereNull('created_by')), 'created_at'),
            'owner_id'
        );

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
            ->map(function ($u) use ($proposalsCreated, $proposalsSubmitted, $assigned, $pickedUp, $valueCreated, $valueSubmitted, $valueAwarded) {
                $row = [
                    'user' => $u->name,
                    'proposals' => (int) ($proposalsCreated[$u->id] ?? 0),
                    'submitted' => (int) ($proposalsSubmitted[$u->id] ?? 0),
                    'assigned' => (int) ($assigned[$u->id] ?? 0),
                    'picked_up' => (int) ($pickedUp[$u->id] ?? 0),
                    'value_created' => (float) ($valueCreated[$u->id] ?? 0),
                    'value_submitted' => (float) ($valueSubmitted[$u->id] ?? 0),
                    'value_awarded' => (float) ($valueAwarded[$u->id] ?? 0),
                ];
                $row['total'] = $row['proposals'] + $row['submitted'];
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
                'assigned' => array_sum($assigned),
                'picked_up' => array_sum($pickedUp),
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
            ...$this->profileRules(),
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
        $user->fill($this->profilePayload($request))->save();
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
            ...$this->profileRules(),
        ]);

        $roleName = $this->resolveRoleName($validated);

        $user->fill(array_merge([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ], $this->profilePayload($request)))->save();
        $user->syncRoles([$roleName]);

        return back()->with('success', 'User updated.');
    }

    /**
     * Validation rules for the expertise/profile fields shared by create + edit.
     * Keyword/expertise/geo lists accept either an array or a comma/newline
     * separated string; they're normalised in profilePayload().
     *
     * @return array<string,string>
     */
    private function profileRules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'department' => 'nullable|string|max:120',
            'pipeline_keywords' => 'nullable',
            'product_expertise' => 'nullable',
            'industry_expertise' => 'nullable',
            'geographic_focus' => 'nullable',
            'min_opportunity_value' => 'nullable|numeric|min:0',
            'max_opportunity_value' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Build the profile column payload from the request. Only fields actually
     * submitted are touched, so partial forms never wipe existing profile data.
     *
     * @return array<string,mixed>
     */
    private function profilePayload(Request $request): array
    {
        $payload = [];

        foreach (['title', 'phone', 'department'] as $field) {
            if ($request->has($field)) {
                $payload[$field] = $request->input($field) ?: null;
            }
        }

        foreach (['pipeline_keywords', 'product_expertise', 'industry_expertise', 'geographic_focus'] as $field) {
            if ($request->has($field)) {
                $payload[$field] = $this->toList($request->input($field));
            }
        }

        foreach (['min_opportunity_value', 'max_opportunity_value'] as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                $payload[$field] = ($value === '' || $value === null) ? null : (float) $value;
            }
        }

        return $payload;
    }

    /**
     * Normalise a list input (array or comma/newline separated string) into a
     * clean array of trimmed, non-empty strings.
     *
     * @return array<int,string>
     */
    private function toList(mixed $value): array
    {
        $items = is_array($value) ? $value : (preg_split('/[\n,]+/', (string) $value) ?: []);

        return array_values(array_filter(
            array_map(fn ($v) => trim((string) $v), $items),
            fn ($v) => $v !== '',
        ));
    }

    /**
     * Connect (or update) a teammate's work email on their behalf. Mirrors the
     * self-service Settings flow, but an admin supplies the user's SMTP host and
     * app password so they don't have to set it up themselves.
     */
    public function connectUserMailbox(Request $request, User $user, MailboxConnectionService $mailboxes): RedirectResponse
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 403);

        $validated = $request->validate(\App\Http\Controllers\Web\SettingsController::mailboxRules());

        $account = $mailboxes->connect($user, $validated);
        if ($account === null) {
            return back()->withErrors(['smtp_password' => "An app password is required to connect {$user->name}'s email."]);
        }

        return back()->with('success', "Work email saved for {$user->name}. Send a test email to confirm it can send.");
    }

    /** Send a verification email to the teammate's own address. */
    public function testUserMailbox(Request $request, User $user, MailboxConnectionService $mailboxes): RedirectResponse
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 403);

        if (!$mailboxes->mailbox($user)?->isConnected()) {
            return back()->with('error', "Connect {$user->name}'s work email first.");
        }

        return $mailboxes->test($user, $request->user()->name)
            ? back()->with('success', "Test email sent to {$user->email}.")
            : back()->with('error', 'Could not send — double-check the host, port, encryption and app password.');
    }

    public function disconnectUserMailbox(Request $request, User $user, MailboxConnectionService $mailboxes): RedirectResponse
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 403);

        $mailboxes->disconnect($user);

        return back()->with('success', "Work email disconnected for {$user->name}.");
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

        // Free the email so it can be reused by a new account, while keeping the
        // soft-deleted record for audit/history. The users.email column is hard-
        // unique, so a deleted user must not keep holding the address — tombstone
        // it with the row id (guaranteed unique even after truncation).
        DB::transaction(function () use ($user) {
            $user->forceFill([
                'email' => Str::limit('deleted+' . $user->id . '+' . $user->email, 255, ''),
                'is_active' => false,
            ])->saveQuietly();
            $user->delete();
        });

        return back()->with('success', 'User deleted.');
    }

    /**
     * Admin-only per-user activity feed: who added / edited / submitted / deleted
     * what, and when. Filterable by user, action and date range; org-scoped.
     */
    public function auditLogs(Request $request): Response
    {
        $orgId = $request->user()->organization_id;
        [$period, $start, $end, $from, $to] = $this->auditRange($request);

        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;
        $event = in_array($request->input('event'), ['created', 'updated', 'deleted'], true) ? $request->input('event') : null;

        $logs = AuditLog::where('organization_id', $orgId)
            ->with(['user:id,name', 'auditable'])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($event, fn ($q) => $q->where('event', $event))
            ->when($start, fn ($q) => $q->where('created_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('created_at', '<=', $end))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $logs->getCollection()->transform(function (AuditLog $log) {
            $subject = $this->auditSubject($log);
            return [
                'id' => $log->id,
                'user' => $log->user?->name ?? 'System',
                'event' => $log->event,
                'action' => $this->auditAction($log),
                'subject_type' => $this->auditTypeLabel($log->auditable_type),
                'subject_label' => $subject['label'],
                'subject_url' => $subject['url'],
                'changes' => $this->auditChanges($log),
                'at' => $log->created_at?->toIso8601String(),
            ];
        });

        return Inertia::render('Admin/AuditLogs', [
            'logs' => $logs,
            'filters' => [
                'user_id' => $userId ? (string) $userId : null,
                'event' => $event,
                'period' => $period,
                'from' => $from,
                'to' => $to,
            ],
            'users' => User::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
            'events' => [
                ['value' => 'created', 'label' => 'Added'],
                ['value' => 'updated', 'label' => 'Edited / status'],
                ['value' => 'deleted', 'label' => 'Deleted'],
            ],
        ]);
    }

    /** @return array{0:string,1:?\Illuminate\Support\Carbon,2:?\Illuminate\Support\Carbon,3:?string,4:?string} */
    private function auditRange(Request $request): array
    {
        $parse = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)
            ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $v) : null;
        $from = $parse($request->input('from'));
        $to = $parse($request->input('to'));

        if ($from && $to) {
            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }
            return ['custom', $from->copy()->startOfDay(), $to->copy()->endOfDay(), $from->toDateString(), $to->toDateString()];
        }

        $period = in_array($request->input('period'), ['day', 'week', 'month', 'year', 'all'], true)
            ? $request->input('period') : 'month';
        $start = match ($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => null,
        };

        return [$period, $start, null, null, null];
    }

    private function auditTypeLabel(string $type): string
    {
        return [
            'ProposalSubmission' => 'Proposal',
            'Opportunity' => 'Opportunity',
            'Company' => 'Company',
            'Contact' => 'Contact',
            'Agency' => 'Agency',
            'FollowUp' => 'Message',
            'Contract' => 'Contract',
            'ComplianceItem' => 'Compliance item',
            'ProposalTemplate' => 'Template',
            'ProposalFile' => 'File',
        ][class_basename($type)] ?? class_basename($type);
    }

    /** @return array{label:string,url:?string} */
    private function auditSubject(AuditLog $log): array
    {
        $m = $log->auditable; // null for hard-/soft-deleted subjects
        $fallback = ['label' => $this->auditTypeLabel($log->auditable_type) . ' #' . $log->auditable_id, 'url' => null];
        if (!$m) {
            return $fallback;
        }

        return match (class_basename($log->auditable_type)) {
            'ProposalSubmission' => ['label' => trim(($m->proposal_number ? $m->proposal_number . ' — ' : '') . $m->project_name), 'url' => "/proposals/{$m->id}"],
            'Opportunity' => ['label' => $m->title, 'url' => "/opportunities/{$m->id}"],
            'Company' => ['label' => $m->name, 'url' => "/companies/{$m->id}"],
            'Contact' => ['label' => trim("{$m->first_name} {$m->last_name}"), 'url' => "/contacts/{$m->id}"],
            'Agency' => ['label' => $m->name, 'url' => "/agencies/{$m->id}"],
            'FollowUp' => ['label' => $m->subject ?: 'Inbox message', 'url' => '/follow-ups'],
            'Contract' => ['label' => $m->contract_number ?: 'Contract', 'url' => '/contracts'],
            'ComplianceItem' => ['label' => $m->name, 'url' => '/compliance'],
            'ProposalTemplate' => ['label' => $m->title, 'url' => '/templates'],
            'ProposalFile' => ['label' => $m->display_name, 'url' => "/proposals/{$m->proposal_submission_id}"],
            default => $fallback,
        };
    }

    private function auditAction(AuditLog $log): string
    {
        if ($log->event === 'created') {
            return 'Added';
        }
        if ($log->event === 'deleted') {
            return 'Deleted';
        }

        $new = $log->new_values ?? [];
        if (array_key_exists('status', $new) && class_basename($log->auditable_type) === 'ProposalSubmission') {
            $status = (string) $new['status'];
            try {
                $label = \App\Enums\ProposalStatus::from($status)->label();
            } catch (\Throwable) {
                $label = ucfirst(str_replace('_', ' ', $status));
            }
            return $status === 'submitted' ? 'Submitted' : "Status → {$label}";
        }

        return 'Edited';
    }

    /** @return array<int,string> */
    private function auditChanges(AuditLog $log): array
    {
        if ($log->event !== 'updated') {
            return [];
        }
        return array_values(array_map(
            fn ($k) => ucfirst(str_replace('_', ' ', $k)),
            array_keys($log->new_values ?? [])
        ));
    }
}
