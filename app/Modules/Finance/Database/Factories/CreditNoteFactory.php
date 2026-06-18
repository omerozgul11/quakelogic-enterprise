<?php

namespace App\Modules\Finance\Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Finance\Enums\CreditNoteStatus;
use App\Modules\Finance\Models\CreditNote;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'number' => 'CN-2026-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'amount' => $this->faker->randomFloat(2, 50, 5000),
            'currency' => 'USD',
            'reason' => $this->faker->randomElement(['Overcharge', 'Returned goods', 'Goodwill', 'Billing error']),
            'status' => CreditNoteStatus::Open->value,
            'issued_at' => now()->toDateString(),
        ];
    }
}
