<?php

namespace App\Enums;

enum CaptureStage: string
{
    case Discovery = 'discovery';
    case Qualification = 'qualification';
    case Pursuit = 'pursuit';
    case ProposalDevelopment = 'proposal_development';
    case Submission = 'submission';
    case Evaluation = 'evaluation';
    case Award = 'award';
    case Execution = 'execution';

    public function label(): string
    {
        return match($this) {
            self::Discovery => 'Discovery',
            self::Qualification => 'Qualification',
            self::Pursuit => 'Pursuit',
            self::ProposalDevelopment => 'Proposal Development',
            self::Submission => 'Submission',
            self::Evaluation => 'Evaluation',
            self::Award => 'Award',
            self::Execution => 'Execution',
        };
    }

    public function order(): int
    {
        return match($this) {
            self::Discovery => 1,
            self::Qualification => 2,
            self::Pursuit => 3,
            self::ProposalDevelopment => 4,
            self::Submission => 5,
            self::Evaluation => 6,
            self::Award => 7,
            self::Execution => 8,
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Discovery => 'blue',
            self::Qualification => 'indigo',
            self::Pursuit => 'purple',
            self::ProposalDevelopment => 'orange',
            self::Submission => 'yellow',
            self::Evaluation => 'teal',
            self::Award => 'green',
            self::Execution => 'emerald',
        };
    }

    public function allowedTransitions(): array
    {
        return match($this) {
            self::Discovery => [self::Qualification],
            self::Qualification => [self::Pursuit, self::Discovery],
            self::Pursuit => [self::ProposalDevelopment, self::Qualification],
            self::ProposalDevelopment => [self::Submission, self::Pursuit],
            self::Submission => [self::Evaluation, self::ProposalDevelopment],
            self::Evaluation => [self::Award, self::Submission],
            self::Award => [self::Execution, self::Evaluation],
            self::Execution => [],
        };
    }

    public function canTransitionTo(CaptureStage $target): bool
    {
        return in_array($target, $this->allowedTransitions());
    }
}
