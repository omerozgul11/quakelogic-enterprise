<?php

namespace App\Enums;

enum AiJobStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case NeedsReview = 'needs_review';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::NeedsReview => 'Needs Review',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending => 'gray',
            self::Processing => 'blue',
            self::NeedsReview => 'yellow',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }
}
