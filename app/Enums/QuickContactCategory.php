<?php

namespace App\Enums;

/**
 * Buckets for the CRM "Quick Contacts" rolodex — frequently-dialed numbers that
 * aren't people at client companies (banks, carriers, agencies, internal desks).
 */
enum QuickContactCategory: string
{
    case Banking = 'banking';
    case Shipping = 'shipping';
    case Vendor = 'vendor';
    case Government = 'government';
    case Insurance = 'insurance';
    case Support = 'support';
    case Internal = 'internal';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Banking => 'Banking & Finance',
            self::Shipping => 'Shipping & Carriers',
            self::Vendor => 'Vendors & Suppliers',
            self::Government => 'Government & Agencies',
            self::Insurance => 'Insurance',
            self::Support => 'IT & Support',
            self::Internal => 'Internal',
            self::Other => 'Other',
        };
    }

    /** Maps to the {@see resources/js/Components/ui/Pill} palette. */
    public function color(): string
    {
        return match ($this) {
            self::Banking => 'green',
            self::Shipping => 'amber',
            self::Vendor => 'indigo',
            self::Government => 'blue',
            self::Insurance => 'red',
            self::Support => 'blue',
            self::Internal => 'gray',
            self::Other => 'gray',
        };
    }

    /** @return array<int,array{value:string,label:string,color:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'color' => $c->color(),
        ], self::cases());
    }
}
