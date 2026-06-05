<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case Parsed = 'parsed';
    case NeedsReview = 'needs_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match($this) {
            self::Uploaded => 'Uploaded',
            self::Processing => 'Processing',
            self::Parsed => 'Parsed',
            self::NeedsReview => 'Needs Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Uploaded => 'gray',
            self::Processing => 'blue',
            self::Parsed => 'indigo',
            self::NeedsReview => 'yellow',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Archived => 'slate',
        };
    }
}
