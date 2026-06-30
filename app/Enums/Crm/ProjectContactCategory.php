<?php

namespace App\Enums\Crm;

/**
 * The role a project stakeholder contact plays on the customer/site side —
 * who the field engineer calls for access, receiving, IT, security or an
 * emergency once on site.
 */
enum ProjectContactCategory: string
{
    case Procurement = 'procurement';
    case ProjectManager = 'project_manager';
    case Facilities = 'facilities';
    case It = 'it';
    case Security = 'security';
    case Accounting = 'accounting';
    case Receiving = 'receiving';
    case Maintenance = 'maintenance';
    case Operations = 'operations';
    case FieldSupervisor = 'field_supervisor';
    case CustomerEngineer = 'customer_engineer';
    case Emergency = 'emergency';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Procurement => 'Procurement Officer',
            self::ProjectManager => 'Project Manager',
            self::Facilities => 'Facilities',
            self::It => 'IT',
            self::Security => 'Security',
            self::Accounting => 'Accounting',
            self::Receiving => 'Receiving',
            self::Maintenance => 'Maintenance',
            self::Operations => 'Operations',
            self::FieldSupervisor => 'Field Supervisor',
            self::CustomerEngineer => 'Customer Engineer',
            self::Emergency => 'Emergency Contact',
            self::Other => 'Other',
        };
    }

    /** Pill-safe colour. */
    public function color(): string
    {
        return match ($this) {
            self::Procurement => 'indigo',
            self::ProjectManager => 'blue',
            self::Facilities => 'cyan',
            self::It => 'purple',
            self::Security => 'amber',
            self::Accounting => 'green',
            self::Receiving => 'teal',
            self::Maintenance => 'orange',
            self::Operations => 'sky',
            self::FieldSupervisor => 'violet',
            self::CustomerEngineer => 'pink',
            self::Emergency => 'red',
            self::Other => 'gray',
        };
    }

    /** @return array<int,array{value:string,label:string,color:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $c) => ['value' => $c->value, 'label' => $c->label(), 'color' => $c->color()],
            self::cases(),
        );
    }
}
