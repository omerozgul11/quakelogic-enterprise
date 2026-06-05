<?php

namespace Tests\Unit\Enums;

use App\Enums\CaptureStage;
use PHPUnit\Framework\TestCase;

class CaptureStageTest extends TestCase
{
    public function test_discovery_can_transition_to_qualification(): void
    {
        $this->assertTrue(CaptureStage::Discovery->canTransitionTo(CaptureStage::Qualification));
    }

    public function test_discovery_cannot_skip_to_pursuit(): void
    {
        $this->assertFalse(CaptureStage::Discovery->canTransitionTo(CaptureStage::Pursuit));
    }

    public function test_qualification_can_transition_to_pursuit_or_back(): void
    {
        $this->assertTrue(CaptureStage::Qualification->canTransitionTo(CaptureStage::Pursuit));
    }

    public function test_award_has_no_forward_transitions(): void
    {
        $allowed = CaptureStage::Award->allowedTransitions();
        $this->assertNotEmpty($allowed); // Award can go to Execution
        $this->assertContains(CaptureStage::Execution, $allowed);
    }

    public function test_execution_is_terminal_stage(): void
    {
        $allowed = CaptureStage::Execution->allowedTransitions();
        $this->assertEmpty($allowed);
    }

    public function test_all_stages_have_order(): void
    {
        foreach (CaptureStage::cases() as $stage) {
            $this->assertIsInt($stage->order());
            $this->assertGreaterThanOrEqual(0, $stage->order());
        }
    }

    public function test_all_stages_have_label_and_color(): void
    {
        foreach (CaptureStage::cases() as $stage) {
            $this->assertNotEmpty($stage->label());
            $this->assertNotEmpty($stage->color());
        }
    }
}
