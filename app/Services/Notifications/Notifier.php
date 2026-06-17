<?php

namespace App\Services\Notifications;

use App\Models\Opportunity;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Notifications\ActivityNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Fans in-app alerts out to the relevant users within an organization.
 */
class Notifier
{
    public function proposalCreated(ProposalSubmission $proposal, User $actor): void
    {
        $value = $proposal->proposal_value
            ? ' · $' . number_format((float) $proposal->proposal_value)
            : '';

        $this->fanOut($actor, 'new_proposal', [
            'type' => 'proposal',
            'title' => 'New proposal: ' . $proposal->project_name,
            'message' => trim($proposal->proposal_number . ' created by ' . $actor->name . $value),
            'url' => route('proposals.show', $proposal),
            'icon' => 'file-text',
        ], alsoNotify: array_filter([$proposal->proposal_manager_id, $proposal->owner_id]));
    }

    /**
     * Tell a user they've just been made the owner of a proposal (e.g. an admin
     * assigned it to them). Only the new owner is notified, and never when a
     * user assigns a proposal to themselves.
     */
    public function proposalAssigned(ProposalSubmission $proposal, User $newOwner, User $actor): void
    {
        if ($newOwner->id === $actor->id) {
            return;
        }
        if (($newOwner->notification_preferences['channels']['assignment'] ?? true) === false) {
            return;
        }

        $newOwner->notify(new ActivityNotification([
            'type' => 'assignment',
            'title' => 'You were assigned a proposal',
            'message' => trim($proposal->proposal_number . ' · ' . $proposal->project_name . ' — assigned by ' . $actor->name),
            'url' => route('proposals.show', $proposal),
            'icon' => 'user-check',
        ]));
    }

    /**
     * Remind everyone working a proposal (owner, manager, creator, team) that
     * its deadline is approaching. Sent once per run by the daily reminder
     * command, for proposals due within the next few days.
     */
    public function proposalDeadline(ProposalSubmission $proposal, int $daysLeft): void
    {
        $userIds = array_values(array_filter(array_unique(array_merge(
            [$proposal->owner_id, $proposal->proposal_manager_id, $proposal->created_by],
            $proposal->teamMembers->pluck('user_id')->all(),
        ))));
        if (!$userIds) {
            return;
        }

        $recipients = User::whereIn('id', $userIds)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u) => ($u->notification_preferences['channels']['deadline'] ?? true) !== false);
        if ($recipients->isEmpty()) {
            return;
        }

        $when = $daysLeft <= 0 ? 'due today' : ($daysLeft === 1 ? 'due tomorrow' : "due in {$daysLeft} days");
        $on = $proposal->due_date ? ' (' . $proposal->due_date->format('M j') . ')' : '';

        Notification::send($recipients, new ActivityNotification([
            'type' => 'deadline',
            'title' => 'Deadline approaching: ' . $proposal->project_name,
            'message' => trim($proposal->proposal_number . ' is ' . $when . $on),
            'url' => route('proposals.show', $proposal),
            'icon' => 'alarm-clock',
        ]));
    }

    public function opportunityCreated(Opportunity $opportunity, User $actor): void
    {
        $this->fanOut($actor, 'new_opportunity', [
            'type' => 'opportunity',
            'title' => 'New opportunity: ' . $opportunity->title,
            'message' => trim(($opportunity->agency_name ? $opportunity->agency_name . ' · ' : '') . 'added by ' . $actor->name),
            'url' => route('opportunities.show', $opportunity),
            'icon' => 'target',
        ], alsoNotify: array_filter([$opportunity->assigned_to, $opportunity->owner_id]));
    }

    /**
     * Tell a user they've just been assigned an opportunity (e.g. an admin or
     * the AI assignment engine routed it to them). Only the new owner is
     * notified, never when a user assigns to themselves.
     */
    public function opportunityAssigned(Opportunity $opportunity, User $newOwner, User $actor): void
    {
        if ($newOwner->id === $actor->id) {
            return;
        }
        if (($newOwner->notification_preferences['channels']['assignment'] ?? true) === false) {
            return;
        }

        $newOwner->notify(new ActivityNotification([
            'type' => 'assignment',
            'title' => 'You were assigned an opportunity',
            'message' => trim($opportunity->title . ($opportunity->agency_name ? ' · ' . $opportunity->agency_name : '') . ' — assigned by ' . $actor->name),
            'url' => route('opportunities.show', $opportunity),
            'icon' => 'user-check',
        ]));
    }

    /**
     * A user has claimed (and locked) ownership of an opportunity by moving it
     * to "In Progress". Alerts the organization's executives/admins so ownership
     * is always visible from the top.
     */
    public function opportunityClaimed(Opportunity $opportunity, User $claimer): void
    {
        $admins = User::where('organization_id', $opportunity->organization_id)
            ->where('is_active', true)
            ->where('id', '!=', $claimer->id)
            ->role(['Super Admin', 'CEO'])
            ->get()
            ->filter(fn (User $u) => ($u->notification_preferences['channels']['assignment'] ?? true) !== false);
        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new ActivityNotification([
            'type' => 'assignment',
            'title' => 'Opportunity claimed: ' . $opportunity->title,
            'message' => trim($claimer->name . ' took ownership' . ($opportunity->agency_name ? ' · ' . $opportunity->agency_name : '') . '.'),
            'url' => route('opportunities.show', $opportunity),
            'icon' => 'lock',
        ]));
    }

    /**
     * Notify every active user in the actor's organization (excluding the actor)
     * who has the given channel enabled in their preferences.
     *
     * @param  array<int,int>  $alsoNotify
     */
    private function fanOut(User $actor, string $channel, array $payload, array $alsoNotify = []): void
    {
        $recipients = User::where('organization_id', $actor->organization_id)
            ->where('is_active', true)
            ->where('id', '!=', $actor->id)
            ->get();

        if ($alsoNotify) {
            $extra = User::whereIn('id', $alsoNotify)->get();
            $recipients = $recipients->merge($extra)->unique('id')->reject(fn ($u) => $u->id === $actor->id);
        }

        // Respect each recipient's channel preference (defaults to on).
        $recipients = $recipients->filter(fn (User $u) => ($u->notification_preferences['channels'][$channel] ?? true) !== false);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new ActivityNotification($payload));
    }

    /**
     * No-contact escalation: a proposal has gone too long without logged client
     * contact. Tier 30 hits the owner, 45 adds the manager, 60 adds admins.
     * Recipients are resolved by ProposalHealthService.
     *
     * @param  \Illuminate\Support\Collection<int,User>  $recipients
     */
    public function proposalHealthEscalation(ProposalSubmission $proposal, int $tier, int $days, \Illuminate\Support\Collection $recipients): void
    {
        $recipients = $recipients->filter(
            fn (User $u) => ($u->notification_preferences['channels']['deadline'] ?? true) !== false
        );
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new ActivityNotification([
            'type' => 'deadline',
            'title' => "No client contact in {$days} days: " . $proposal->project_name,
            'message' => trim($proposal->proposal_number . " — needs follow-up (escalation tier {$tier}d). Log a client contact to clear this."),
            'url' => route('proposals.show', $proposal),
            'icon' => 'heart-pulse',
        ]));
    }

    /**
     * Assignment-inaction escalation: an assigned opportunity has gone
     * un-actioned past a 24/48/72/96-hour tier. Recipients climb owner →
     * manager → admin and are resolved by OpportunityEscalationService.
     *
     * @param  \Illuminate\Support\Collection<int,User>  $recipients
     */
    public function opportunityEscalation(Opportunity $opportunity, int $tier, \Illuminate\Support\Collection $recipients, string $note): void
    {
        $recipients = $recipients->filter(
            fn (User $u) => ($u->notification_preferences['channels']['deadline'] ?? true) !== false
        );
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new ActivityNotification([
            'type' => 'deadline',
            'title' => "Opportunity un-actioned {$tier}h: " . $opportunity->title,
            'message' => trim($note),
            'url' => route('opportunities.show', $opportunity),
            'icon' => $tier >= 96 ? 'alert-triangle' : 'alarm-clock',
        ]));
    }

    /**
     * Pending-award daily nudge: a submitted proposal is awaiting a decision.
     * Sent every day to the owner/manager/creator/team until it moves to
     * awarded, lost, or cancelled.
     */
    public function proposalPendingAward(ProposalSubmission $proposal, int $daysWaiting): void
    {
        $userIds = array_values(array_filter(array_unique(array_merge(
            [$proposal->owner_id, $proposal->proposal_manager_id, $proposal->created_by],
            $proposal->teamMembers->pluck('user_id')->all(),
        ))));
        if (!$userIds) {
            return;
        }

        $recipients = User::whereIn('id', $userIds)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u) => ($u->notification_preferences['channels']['deadline'] ?? true) !== false);
        if ($recipients->isEmpty()) {
            return;
        }

        $waited = $daysWaiting > 0 ? " ({$daysWaiting}d waiting)" : '';

        Notification::send($recipients, new ActivityNotification([
            'type' => 'deadline',
            'title' => 'Awaiting award decision: ' . $proposal->project_name,
            'message' => trim($proposal->proposal_number . ' is pending award' . $waited . ' — check for a decision or follow up with the client.'),
            'url' => route('proposals.show', $proposal),
            'icon' => 'hourglass',
        ]));
    }
}
