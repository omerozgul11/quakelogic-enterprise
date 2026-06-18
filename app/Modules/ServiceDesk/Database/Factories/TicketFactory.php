<?php

namespace App\Modules\ServiceDesk\Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Modules\ServiceDesk\Enums\TicketPriority;
use App\Modules\ServiceDesk\Enums\TicketStatus;
use App\Modules\ServiceDesk\Enums\TicketType;
use App\Modules\ServiceDesk\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'number' => 'TKT-2026-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'subject' => $this->faker->sentence(5),
            'description' => $this->faker->paragraph(),
            'type' => TicketType::Support->value,
            'status' => TicketStatus::New->value,
            'priority' => TicketPriority::Normal->value,
            'opened_at' => now(),
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => ['priority' => TicketPriority::High->value, 'due_at' => now()->subDay(), 'status' => TicketStatus::Open->value]);
    }
}
