<?php

namespace App\Modules\ExpenseTracker\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Check = 'check';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Card => 'Card',
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank Transfer',
            self::Check => 'Check',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Card => 'blue',
            self::Cash => 'green',
            self::BankTransfer => 'indigo',
            self::Check => 'slate',
            self::Other => 'gray',
        };
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $m) => ['value' => $m->value, 'label' => $m->label()], self::cases());
    }
}
