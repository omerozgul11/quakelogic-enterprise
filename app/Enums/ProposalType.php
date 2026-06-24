<?php

namespace App\Enums;

enum ProposalType: string
{
    case Rfi = 'rfi';
    case Rfq = 'rfq';
    case Rfp = 'rfp';
    case NoticeOfIntent = 'notice_of_intent';
    case Proposal = 'proposal';

    public function label(): string
    {
        return match ($this) {
            self::Rfi => 'RFI',
            self::Rfq => 'RFQ',
            self::Rfp => 'RFP',
            self::NoticeOfIntent => 'Notice of Intent',
            self::Proposal => 'Proposal',
        };
    }

    /** Spelled-out name, used for option subtitles and tooltips. */
    public function description(): string
    {
        return match ($this) {
            self::Rfi => 'Request for Information',
            self::Rfq => 'Request for Quote',
            self::Rfp => 'Request for Proposal',
            self::NoticeOfIntent => 'Notice of Intent (informational)',
            self::Proposal => 'Proposal / bid',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Rfi => 'slate',
            self::Rfq => 'cyan',
            self::Rfp => 'violet',
            self::NoticeOfIntent => 'amber',
            self::Proposal => 'blue',
        };
    }

    /**
     * RFIs and Notices of Intent are informational only — they carry no dollar
     * value, so the value fields are hidden for them across the UI.
     */
    public function hasValue(): bool
    {
        return $this !== self::Rfi && $this !== self::NoticeOfIntent;
    }

    /** @return array<int,array{value:string,label:string,description:string,has_value:bool}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => [
            'value' => $t->value,
            'label' => $t->label(),
            'description' => $t->description(),
            'has_value' => $t->hasValue(),
        ], self::cases());
    }
}
