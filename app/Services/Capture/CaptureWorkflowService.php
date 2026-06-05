<?php

namespace App\Services\Capture;

use App\Enums\CaptureStage;
use App\Models\CapturePlan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CaptureWorkflowService
{
    /**
     * Transition a capture plan to a new stage.
     */
    public function transitionStage(CapturePlan $plan, CaptureStage $newStage, User $user, ?string $notes = null): CapturePlan
    {
        $currentStage = $plan->stage;

        if ($currentStage === $newStage) {
            throw ValidationException::withMessages(['stage' => 'Capture plan is already in this stage.']);
        }

        if (!$currentStage->canTransitionTo($newStage)) {
            throw ValidationException::withMessages([
                'stage' => "Cannot transition from {$currentStage->label()} to {$newStage->label()}. Allowed: "
                    . collect($currentStage->allowedTransitions())->map(fn($s) => $s->label())->join(', '),
            ]);
        }

        $plan->update(['stage' => $newStage->value]);

        $plan->stageHistory()->create([
            'changed_by' => $user->id,
            'from_stage' => $currentStage->value,
            'to_stage' => $newStage->value,
            'notes' => $notes,
            'changed_at' => now(),
        ]);

        // Sync opportunity capture_stage
        $plan->opportunity()->update(['capture_stage' => $newStage->value]);

        return $plan->refresh();
    }
}
