<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\ProposalSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FollowUpController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Gmail-style: each proposal the user is involved with is a "conversation"
        // holding its follow-up thread. Only an admin sees everyone's proposals.
        $proposalsQuery = ProposalSubmission::forOrganization($user->organization_id)
            ->with(['followUps' => fn ($q) => $q
                ->with(['assignedTo:id,name', 'contact:id,first_name,last_name,email'])
                ->orderBy('scheduled_date')->orderBy('id')]);

        if (!$user->hasRole('Super Admin')) {
            $proposalsQuery->where(fn ($q) => $q
                ->where('owner_id', $user->id)
                ->orWhere('proposal_manager_id', $user->id)
                ->orWhereHas('teamMembers', fn ($tm) => $tm->where('user_id', $user->id)));
        }

        $proposals = $proposalsQuery->get(['id', 'proposal_number', 'project_name', 'status']);

        $threads = $proposals->map(function ($p) {
            $messages = $p->followUps->map(fn ($f) => [
                'id' => $f->id,
                'subject' => $f->subject,
                'message' => $f->message,
                'type' => $f->type,
                'status' => $f->status instanceof \BackedEnum ? $f->status->value : $f->status,
                'scheduled_date' => $f->scheduled_date?->format('Y-m-d'),
                'sent_at' => $f->sent_at?->toIso8601String(),
                'created_at' => $f->created_at?->toIso8601String(),
                'author' => $f->assignedTo?->name,
                'contact' => $f->contact ? trim($f->contact->first_name . ' ' . $f->contact->last_name) : null,
                'automated' => (bool) $f->is_automated,
            ])->values();

            return [
                'id' => $p->id,
                'proposal_number' => $p->proposal_number,
                'project_name' => $p->project_name,
                'status' => $p->status instanceof \BackedEnum ? $p->status->value : $p->status,
                'messages' => $messages,
                'count' => $messages->count(),
                'last_at' => $p->followUps->max('created_at')?->toIso8601String(),
            ];
        })
            ->sortByDesc(fn ($t) => $t['last_at'] ?? '')
            ->values();

        $mailbox = $user->emailAccount;

        return Inertia::render('FollowUps/Index', [
            'threads' => $threads,
            'proposals' => $proposals->map(fn ($p) => [
                'id' => $p->id,
                'proposal_number' => $p->proposal_number,
                'project_name' => $p->project_name,
            ])->values(),
            'mailbox' => [
                'connected' => (bool) $mailbox?->isConnected(),
                'email' => $mailbox?->email,
            ],
        ]);
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
                ->where('owner_id', $user->id)
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
        // Can't post a message onto a proposal you're not attached to.
        abort_unless($this->canAccessProposal($user, $validated['proposal_submission_id'] ?? null), 403);

        FollowUp::create([
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'assigned_to' => $validated['assigned_to'] ?? $user->id,
            'proposal_submission_id' => $validated['proposal_submission_id'] ?? null,
            'opportunity_id' => $validated['opportunity_id'] ?? null,
            'contact_id' => $validated['contact_id'] ?? null,
            'type' => $validated['type'] ?? 'note',
            'subject' => $validated['subject'] ?? 'Follow-up',
            'message' => $validated['message'],
            'scheduled_date' => $validated['scheduled_date'] ?? now()->toDateString(),
            'status' => 'scheduled',
        ]);

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
