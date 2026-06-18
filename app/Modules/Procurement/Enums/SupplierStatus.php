<?php

namespace App\Modules\Procurement\Enums;

enum SupplierStatus: string
{
    case Active = 'active';
    case PendingApproval = 'pending_approval';
    case OnHold = 'on_hold';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::PendingApproval => 'Pending Approval',
            self::OnHold => 'On Hold',
            self::Inactive => 'Inactive',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::PendingApproval => 'amber',
            self::OnHold => 'indigo',
            self::Inactive => 'gray',
        };
    }

    /** A supplier you can raise new purchase orders against. */
    public function canOrder(): bool
    {
        return $this === self::Active;
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
