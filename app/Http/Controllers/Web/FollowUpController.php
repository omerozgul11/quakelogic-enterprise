<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Notifications\ActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FollowUpController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $viewerId = $user->id;

        // Gmail-style: each proposal the user is involved with is a "conversation"
        // holding its follow-up thread. Only an admin sees everyone's proposals.
        $proposalsQuery = ProposalSubmission::forOrganization($user->organization_id)
            ->with(['followUps' => fn ($q) => $q
                ->with(['assignedTo:id,name', 'createdBy:id,name', 'contact:id,first_name,last_name,email'])
                ->orderBy('scheduled_date')->orderBy('id')]);

        if (!$user->hasRole('Super Admin')) {
            $proposalsQuery->where(fn ($q) => $q
                ->where('created_by', $user->id)
                ->orWhere('owner_id', $user->id)
                ->orWhere('proposal_manager_id', $user->id)
                ->orWhereHas('teamMembers', fn ($tm) => $tm->where('user_id', $user->id)));
        }

        $proposals = $proposalsQuery->get(['id', 'proposal_number', 'project_name', 'status']);

        $threads = $proposals->map(fn ($p) => [
            'key' => 'proposal-' . $p->id,
            'kind' => 'proposal',
            'proposal_id' => $p->id,
            'recipient_id' => null,
            'proposal_number' => $p->proposal_number,
            'project_name' => $p->project_name,
            'status' => $p->status instanceof \BackedEnum ? $p->status->value : $p->status,
            'messages' => $p->followUps->map(fn ($f) => $this->presentMessage($f, $viewerId))->values(),
            'count' => $p->followUps->count(),
            'last_at' => $p->followUps->max('created_at')?->toIso8601String(),
        ]);

        // Proposal-less follow-ups the user is a participant in: direct messages
        // to/from coworkers, plus self/system notes (digests, general notes).
        // These stay private to the two participants — even admins don't see
        // other people's direct messages here.
        $general = FollowUp::where('organization_id', $user->organization_id)
            ->whereNull('proposal_submission_id')
            ->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id))
            ->with(['assignedTo:id,name', 'createdBy:id,name', 'contact:id,first_name,last_name,email'])
            ->orderBy('created_at')->orderBy('id')
            ->get();

        $selfMessages = collect();
        $byUser = []; // other user id => FollowUp[]
        foreach ($general as $f) {
            $a = $f->created_by;
            $b = $f->assigned_to;
            $other = (!$b || $a === $b) ? null : ($a === $viewerId ? $b : ($b === $viewerId ? $a : null));
            if ($other === null) {
                $selfMessages->push($f);
            } else {
                $byUser[$other][] = $f;
            }
        }

        foreach ($byUser as $otherId => $msgs) {
            $col = collect($msgs);
            $name = $col->map(fn ($f) => $f->created_by === $otherId ? $f->createdBy?->name : $f->assignedTo?->name)
                ->filter()->first() ?? 'Teammate';
            $threads->push([
                'key' => 'direct-' . $otherId,
                'kind' => 'direct',
                'proposal_id' => null,
                'recipient_id' => $otherId,
                'proposal_number' => null,
                'project_name' => $name,
                'status' => null,
                'messages' => $col->map(fn ($f) => $this->presentMessage($f, $viewerId))->values(),
                'count' => $col->count(),
                'last_at' => $col->max('created_at')?->toIso8601String(),
            ]);
        }

        $threads = $threads->push([
            'key' => 'general',
            'kind' => 'general',
            'proposal_id' => null,
            'recipient_id' => null,
            'proposal_number' => null,
            'project_name' => 'Daily Summary',
            'status' => null,
            'messages' => $selfMessages->map(fn ($f) => $this->presentMessage($f, $viewerId))->values(),
            'count' => $selfMessages->count(),
            'last_at' => $selfMessages->max('created_at')?->toIso8601String(),
        ]);

        // Pinned conversations float to the top (most-recent first within each
        // group). The Daily Summary (general) thread is always pinned above
        // everything else so digests/self-notes are one click away.
        $pinned = \App\Models\InboxPin::where('user_id', $user->id)->pluck('thread_key')->flip();
        $threads = $threads
            ->map(fn ($t) => ['pinned' => $t['key'] === 'general' || $pinned->has($t['key'])] + $t)
            ->sort(function ($a, $b) {
                $aGeneral = $a['key'] === 'general';
                $bGeneral = $b['key'] === 'general';
                if ($aGeneral !== $bGeneral) {
                    return $aGeneral ? -1 : 1;
                }
                if ($a['pinned'] !== $b['pinned']) {
                    return $a['pinned'] ? -1 : 1;
                }
                return strcmp($b['last_at'] ?? '', $a['last_at'] ?? '');
            })
            ->values();

        $mailbox = $user->emailAccount;

        return Inertia::render('FollowUps/Index', [
            'threads' => $threads,
            'proposals' => $proposals->map(fn ($p) => [
                'id' => $p->id,
                'proposal_number' => $p->proposal_number,
                'project_name' => $p->project_name,
            ])->values(),
            // Coworkers you can message directly.
            'users' => User::where('organization_id', $user->organization_id)
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'mailbox' => [
                'connected' => (bool) $mailbox?->isConnected(),
                'email' => $mailbox?->email,
            ],
        ]);
    }

    /** Shape one follow-up record for the inbox thread view. */
    private function presentMessage(FollowUp $f, int $viewerId): array
    {
        return [
            'id' => $f->id,
            'subject' => $f->subject,
            'message' => $f->message,
            'type' => $f->type,
            'status' => $f->status instanceof \BackedEnum ? $f->status->value : $f->status,
            'scheduled_date' => $f->scheduled_date?->format('Y-m-d'),
            'sent_at' => $f->sent_at?->toIso8601String(),
            'created_at' => $f->created_at?->toIso8601String(),
            'author' => $f->createdBy?->name ?? $f->assignedTo?->name,
            'to' => ($f->assigned_to && $f->assigned_to !== $f->created_by) ? $f->assignedTo?->name : null,
            'mine' => $f->created_by === $viewerId,
            'contact' => $f->contact ? trim($f->contact->first_name . ' ' . $f->contact->last_name) : null,
            'automated' => (bool) $f->is_automated,
            'unread' => $f->isUnreadFor($viewerId),
        ];
    }

    /**
     * Base query over the inbox messages a user may act on (mark read / delete),
     * scoped to their organization. Mirrors canAccess() and the inbox's own
     * visibility: a Super Admin sees every message in the org (the inbox shows
     * them all), while everyone else sees messages assigned to or created by them,
     * or attached to a proposal they're on. markRead() and destroyMany() both use
     * this so the actionable set always matches what the badge counts and the
     * inbox displays — previously markRead wasn't admin-aware, so admins could
     * read messages that never cleared from the unread badge.
     */
    private function accessibleMessages(User $user)
    {
        $query = FollowUp::where('organization_id', $user->organization_id);

        if ($user->hasRole('Super Admin')) {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->where('assigned_to', $user->id)
            ->orWhere('created_by', $user->id)
            ->orWhereHas('proposal', fn ($p) => $p
                ->where('owner_id', $user->id)
                ->orWhere('proposal_manager_id', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhereHas('teamMembers', fn ($tm) => $tm->where('user_id', $user->id))));
    }

    /**
     * Mark a set of inbox messages as read for the viewer (fired when they open a
     * conversation, or via the bulk "Mark read" action). Scoped to the messages
     * the viewer may access, so it can never clear someone else's unread state.
     */
    public function markRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        $ids = collect($request->input('ids', []))->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) {
            return back();
        }

        $this->accessibleMessages($user)
            ->whereIn('id', $ids->all())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    /**
     * Bulk-delete inbox messages. Scoped to the messages the viewer may access, so
     * a user can clear out conversations they can see but never delete messages
     * that aren't theirs. Soft-deletes (and audits) each row individually.
     */
    public function destroyMany(Request $request): RedirectResponse
    {
        $user = $request->user();
        $ids = collect($request->input('ids', []))->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) {
            return back();
        }

        $messages = $this->accessibleMessages($user)->whereIn('id', $ids->all())->get();
        foreach ($messages as $message) {
            $message->delete();
        }

        $count = $messages->count();
        if ($count === 0) {
            return back();
        }

        return back()->with('success', $count === 1 ? 'Message deleted.' : "{$count} messages deleted.");
    }

    /**
     * Pin or unpin a conversation in the viewer's inbox (toggles). Pinned threads
     * float to the top of their conversation list.
     */
    public function togglePin(Request $request): RedirectResponse
    {
        $validated = $request->validate(['key' => 'required|string|max:191']);
        $user = $request->user();

        $existing = \App\Models\InboxPin::where('user_id', $user->id)
            ->where('thread_key', $validated['key'])
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            \App\Models\InboxPin::create([
                'user_id' => $user->id,
                'organization_id' => $user->organization_id,
                'thread_key' => $validated['key'],
            ]);
        }

        return back();
    }

    /**
     * A user may see a follow-up only if they're an admin, it's assigned to or
     * created by them, or they're attached to its proposal (owner/manager/team).
     */
    private function canAccess(\App\Models\User $user, FollowUp $followUp): bool
    {
        if ($user->organization_id !== $followUp->organization_id) {
            return false;
        }
        if ($user->hasRole('Super Admin')) {
            return true;
        }
        if ($followUp->assigned_to === $user->id || $followUp->created_by === $user->id) {
            return true;
        }
        $proposal = $followUp->proposal;
        return $proposal !== null && (
            $proposal->owner_id === $user->id
            || $proposal->proposal_manager_id === $user->id
            || $proposal->teamMembers()->where('user_id', $user->id)->exists()
        );
    }

    private function canAccessProposal(\App\Models\User $user, ?int $proposalId): bool
    {
        if (!$proposalId) {
            return true;
        }
        if ($user->hasRole('Super Admin')) {
            return ProposalSubmission::where('id', $proposalId)->where('organization_id', $user->organization_id)->exists();
        }
        return ProposalSubmission::where('id', $proposalId)
            ->where('organization_id', $user->organization_id)
            ->where(fn ($q) => $q
                ->where('created_by', $user->id)
                ->orWhere('owner_id', $user->id)
                ->orWhere('proposal_manager_id', $user->id)
                ->orWhereHas('teamMembers', fn ($tm) => $tm->where('user_id', $user->id)))
            ->exists();
    }

    public function show(Request $request, FollowUp $followUp): Response
    {
        abort_unless($this->canAccess($request->user(), $followUp), 403);

        $followUp->load([
            'assignedTo:id,name',
            'proposal:id,proposal_number,project_name',
            'opportunity:id,title',
            'contact:id,first_name,last_name,title,email,phone,company_id',
            'contact.company:id,name',
        ]);

        return Inertia::render('FollowUps/Show', [
            'followUp' => $followUp,
            'can' => ['update' => true],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string',
            'scheduled_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
            'proposal_submission_id' => 'nullable|exists:proposal_submissions,id',
            'opportunity_id' => 'nullable|exists:opportunities,id',
            'contact_id' => 'nullable|exists:contacts,id',
        ]);

        $user = $request->user();
        $proposalId = $validated['proposal_submission_id'] ?? null;
        // Can't post a message onto a proposal you're not attached to.
        abort_unless($this->canAccessProposal($user, $proposalId), 403);

        // Direct message: the recipient must be an active coworker in the org.
        $recipient = null;
        if (!empty($validated['assigned_to'])) {
            $recipient = User::where('id', $validated['assigned_to'])
                ->where('organization_id', $user->organization_id)
                ->first();
            abort_unless($recipient !== null, 403);
        }

        FollowUp::create([
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'assigned_to' => $recipient?->id ?? $user->id,
            'proposal_submission_id' => $proposalId,
            'opportunity_id' => $validated['opportunity_id'] ?? null,
            'contact_id' => $validated['contact_id'] ?? null,
            'type' => $validated['type'] ?? 'note',
            'subject' => $validated['subject'] ?? 'Follow-up',
            'message' => $validated['message'],
            'scheduled_date' => $validated['scheduled_date'] ?? now()->toDateString(),
            'status' => 'scheduled',
        ]);

        // Ping the coworker when this is a direct message to them.
        if (!$proposalId && $recipient && $recipient->id !== $user->id) {
            $recipient->notify(new ActivityNotification([
                'type' => 'message',
                'title' => 'New message from ' . $user->name,
                'message' => Str::limit($validated['message'], 90),
                'url' => route('follow-ups.index'),
                'icon' => 'message-square',
            ]));

            return back()->with('success', 'Message sent to ' . $recipient->name . '.');
        }

        return back()->with('success', 'Message added to the thread.');
    }

    public function update(Request $request, FollowUp $followUp): RedirectResponse
    {
        abort_unless($this->canAccess($request->user(), $followUp), 403);

        $validated = $request->validate([
            'status' => 'required|string|in:scheduled,sent,overdue,responded,cancelled',
            'message' => 'nullable|string',
        ]);

        $followUp->status = $validated['status'];
        if (array_key_exists('message', $validated) && $validated['message'] !== null) {
            $followUp->message = $validated['message'];
        }

        if ($validated['status'] === 'sent' && !$followUp->sent_at) {
            $followUp->sent_at = now();
        } elseif ($validated['status'] === 'responded' && !$followUp->responded_at) {
            $followUp->responded_at = now();
        }

        $followUp->save();

        return back()->with('success', 'Follow-up updated.');
    }

    public function destroy(Request $request, FollowUp $followUp): RedirectResponse
    {
        abort_unless($this->canAccess($request->user(), $followUp), 403);

        $followUp->delete();

        return redirect()->route('follow-ups.index')->with('success', 'Follow-up deleted.');
    }
}
