<?php

namespace App\Enums\Crm;

/**
 * The kind of travel arrangement booked for a project trip — flights, lodging,
 * car rental, ground transport, rail, per-diem and incidental costs.
 */
enum TravelType: string
{
    case Flight = 'flight';
    case Lodging = 'lodging';
    case CarRental = 'car_rental';
    case Ground = 'ground';
    case Rail = 'rail';
    case PerDiem = 'per_diem';
    case Parking = 'parking';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Flight => 'Flight',
            self::Lodging => 'Lodging',
            self::CarRental => 'Car rental',
            self::Ground => 'Ground transport',
            self::Rail => 'Rail',
            self::PerDiem => 'Per diem',
            self::Parking => 'Parking',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Flight => 'blue',
            self::Lodging => 'indigo',
            self::CarRental => 'amber',
            self::Ground => 'cyan',
            self::Rail => 'purple',
            self::PerDiem => 'green',
            self::Parking => 'gray',
            self::Other => 'gray',
        };
    }

    /** @return array<int,array{value:string,label:string,color:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $t) => ['value' => $t->value, 'label' => $t->label(), 'color' => $t->color()],
            self::cases(),
        );
    }
}
