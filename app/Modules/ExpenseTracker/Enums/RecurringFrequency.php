<?php

namespace App\Modules\ExpenseTracker\Enums;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

enum RecurringFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Yearly => 'Yearly',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Daily => 'blue',
            self::Weekly => 'cyan',
            self::Monthly => 'indigo',
            self::Quarterly => 'purple',
            self::Yearly => 'teal',
        };
    }

    /** The next occurrence date, advancing $intervalCount periods from $from. */
    public function advance(CarbonInterface $from, int $intervalCount = 1): Carbon
    {
        $steps = max(1, $intervalCount);
        $date = $from instanceof Carbon ? $from->copy() : Carbon::instance($from);

        return match ($this) {
            self::Daily => $date->addDays($steps),
            self::Weekly => $date->addWeeks($steps),
            self::Monthly => $date->addMonthsNoOverflow($steps),
            self::Quarterly => $date->addMonthsNoOverflow(3 * $steps),
            self::Yearly => $date->addYearsNoOverflow($steps),
        };
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $f) => ['value' => $f->value, 'label' => $f->label()], self::cases());
    }
}
