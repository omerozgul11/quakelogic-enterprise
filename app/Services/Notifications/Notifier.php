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
}
