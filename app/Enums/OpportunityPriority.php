<?php

namespace App\Enums;

/**
 * Relevance priority assigned to an opportunity by the scoring engine.
 */
enum OpportunityPriority: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case NotRelevant = 'not_relevant';

    public function label(): string
    {
        return match ($this) {
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
            self::NotRelevant => 'Not Relevant',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::High => 'red',
            self::Medium => 'amber',
            self::Low => 'blue',
            self::NotRelevant => 'gray',
        };
    }

    /** @return array<int,array{value:string,label:string,color:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $p) => ['value' => $p->value, 'label' => $p->label(), 'color' => $p->color()],
            self::cases(),
        );
    }
}
