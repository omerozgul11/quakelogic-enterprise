<?php

namespace App\Modules\AssetManagement\Enums;

/**
 * Asset lifecycle (a focused subset of the full doc lifecycle):
 *   in_stock → assigned → deployed → active → under_maintenance / in_repair
 *   → retired → disposed.
 */
enum AssetStatus: string
{
    case InStock = 'in_stock';
    case Assigned = 'assigned';
    case Deployed = 'deployed';
    case Active = 'active';
    case UnderMaintenance = 'under_maintenance';
    case InRepair = 'in_repair';
    case Retired = 'retired';
    case Disposed = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In Stock',
            self::Assigned => 'Assigned',
            self::Deployed => 'Deployed',
            self::Active => 'Active',
            self::UnderMaintenance => 'Under Maintenance',
            self::InRepair => 'In Repair',
            self::Retired => 'Retired',
            self::Disposed => 'Disposed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'gray',
            self::Assigned => 'blue',
            self::Deployed => 'indigo',
            self::Active => 'green',
            self::UnderMaintenance => 'amber',
            self::InRepair => 'red',
            self::Retired => 'gray',
            self::Disposed => 'red',
        };
    }

    /** Currently in service / deployable. */
    public function isOperational(): bool
    {
        return in_array($this, [self::Assigned, self::Deployed, self::Active], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Retired, self::Disposed], true);
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
