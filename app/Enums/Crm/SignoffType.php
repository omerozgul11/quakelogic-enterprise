<?php

namespace App\Enums\Crm;

/**
 * Who is signing off on a project milestone — the customer-facing acceptances
 * and internal approvals captured with a signature and timestamp.
 */
enum SignoffType: string
{
    case Customer = 'customer';
    case ProjectManager = 'project_manager';
    case FieldEngineer = 'field_engineer';
    case Qa = 'qa';
    case Commissioning = 'commissioning';
    case Acceptance = 'acceptance';
    case Warranty = 'warranty';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::ProjectManager => 'Project Manager',
            self::FieldEngineer => 'Field Engineer',
            self::Qa => 'QA',
            self::Commissioning => 'Commissioning',
            self::Acceptance => 'Acceptance',
            self::Warranty => 'Warranty',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Customer => 'blue',
            self::ProjectManager => 'indigo',
            self::FieldEngineer => 'cyan',
            self::Qa => 'purple',
            self::Commissioning => 'amber',
            self::Acceptance => 'green',
            self::Warranty => 'orange',
        };
    }

    /** A sensible default attestation statement for this sign-off type. */
    public function statement(): string
    {
        return match ($this) {
            self::Customer => 'I confirm the work described has been completed to our satisfaction.',
            self::ProjectManager => 'Reviewed and approved.',
            self::FieldEngineer => 'Installation and setup completed as specified.',
            self::Qa => 'Quality assurance checks have been completed and passed.',
            self::Commissioning => 'The system has been commissioned and is operational.',
            self::Acceptance => 'I accept this installation as complete and in good working order.',
            self::Warranty => 'Warranty terms acknowledged.',
        };
    }

    /** @return array<int,array{value:string,label:string,color:string,statement:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $t) => ['value' => $t->value, 'label' => $t->label(), 'color' => $t->color(), 'statement' => $t->statement()],
            self::cases(),
        );
    }
}
