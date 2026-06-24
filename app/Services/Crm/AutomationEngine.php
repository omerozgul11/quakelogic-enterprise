<?php

namespace App\Services\Crm;

use App\Enums\LeadStatus;
use App\Models\Crm\Automation;
use App\Models\Crm\FollowUp;
use App\Models\Crm\Lead;
use App\Models\User;
use App\Notifications\ActivityNotification;
use Illuminate\Support\Carbon;

/**
 * Evaluates and runs CRM automation rules. Called from the lead controller after
 * a lead is created or its stage changes. Actions are limited to safe in-app
 * effects, and none of them re-fire a lead trigger, so rules cannot loop.
 */
class AutomationEngine
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function leadCreated(Lead $lead, ?User $actor): void
    {
        $this->run('lead.created', $lead, $actor, $lead->status?->value);
    }

    public function leadStageChanged(Lead $lead, LeadStatus $to, ?User $actor): void
    {
        $this->run('lead.stage_changed', $lead, $actor, $to->value);
    }

    private function run(string $event, Lead $lead, ?User $actor, ?string $stage): void
    {
        $automations = Automation::forOrganization($lead->organization_id)
            ->where('is_active', true)
            ->where('trigger_event', $event)
            ->get();

        foreach ($automations as $automation) {
            if (! $this->matches($automation, $lead, $stage)) {
                continue;
            }

            $this->execute($automation, $lead, $actor);
            $automation->forceFill([
                'run_count' => $automation->run_count + 1,
                'last_run_at' => now(),
            ])->saveQuietly();
        }
    }

    private function matches(Automation $automation, Lead $lead, ?string $stage): bool
    {
        $c = $automation->conditions ?? [];

        if (! empty($c['stage']) && $c['stage'] !== $stage) {
            return false;
        }
        if (! empty($c['source']) && strcasecmp((string) $lead->source, (string) $c['source']) !== 0) {
            return false;
        }
        if (isset($c['min_value']) && $c['min_value'] !== '' && (float) $lead->estimated_value < (float) $c['min_value']) {
            return false;
        }

        return true;
    }

    private function execute(Automation $automation, Lead $lead, ?User $actor): void
    {
        foreach (($automation->actions ?? []) as $action) {
            match ($action['type'] ?? '') {
                'create_followup' => $this->createFollowUp($automation, $lead, $action),
                'notify' => $this->notify($automation, $lead, $action),
                'assign_owner' => $this->assignOwner($lead, $action, $actor),
                'log_activity' => $this->activity->system($lead, (string) ($action['body'] ?? 'Automation note'), ['automation_id' => $automation->id]),
                default => null,
            };
        }
    }

    /** @param array<string,mixed> $action */
    private function createFollowUp(Automation $automation, Lead $lead, array $action): void
    {
        $assignee = match ($action['assign'] ?? 'owner') {
            'creator' => $lead->created_by,
            'owner' => $lead->owner_id,
            default => $this->orgUserId($lead->organization_id, $action['assign'] ?? null) ?? $lead->owner_id,
        };

        FollowUp::create([
            'organization_id' => $lead->organization_id,
            'created_by' => $automation->created_by,
            'assigned_to' => $assignee,
            'subject_type' => $lead->getMorphClass(),
            'subject_id' => $lead->id,
            'title' => (string) ($action['title'] ?? 'Follow up'),
            'due_date' => Carbon::now()->addDays((int) ($action['due_in_days'] ?? 0))->toDateString(),
            'priority' => in_array($action['priority'] ?? 'normal', FollowUp::PRIORITIES, true) ? $action['priority'] : 'normal',
            'status' => 'open',
        ]);
    }

    /** @param array<string,mixed> $action */
    private function notify(Automation $automation, Lead $lead, array $action): void
    {
        $userId = ($action['to'] ?? 'owner') === 'owner'
            ? $lead->owner_id
            : $this->orgUserId($lead->organization_id, $action['to'] ?? null);

        $user = $userId ? User::where('organization_id', $lead->organization_id)->where('is_active', true)->find($userId) : null;
        if (! $user) {
            return;
        }

        $user->notify(new ActivityNotification([
            'type' => 'automation',
            'title' => $automation->name,
            'message' => (string) ($action['message'] ?? "Lead: {$lead->title}"),
            'url' => "/crm/leads/{$lead->id}",
            'icon' => 'zap',
        ]));
    }

    /** @param array<string,mixed> $action */
    private function assignOwner(Lead $lead, array $action, ?User $actor): void
    {
        $userId = $this->orgUserId($lead->organization_id, $action['user_id'] ?? null);
        if (! $userId || $userId === $lead->owner_id) {
            return;
        }

        $lead->forceFill(['owner_id' => $userId])->saveQuietly();
        $name = User::find($userId)?->name ?? 'a teammate';
        $this->activity->system($lead, "Reassigned to {$name} by automation");
    }

    private function orgUserId(int $orgId, mixed $candidate): ?int
    {
        if (! is_numeric($candidate)) {
            return null;
        }
        $id = (int) $candidate;

        return User::where('organization_id', $orgId)->where('id', $id)->exists() ? $id : null;
    }
}
