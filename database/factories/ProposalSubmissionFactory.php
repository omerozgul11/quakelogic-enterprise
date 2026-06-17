<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\ProposalSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProposalSubmissionFactory extends Factory
{
    protected $model = ProposalSubmission::class;

    private static int $sequence = 100;

    public function definition(): array
    {
        $year = date('Y');
        $num = ++self::$sequence;
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'proposal_number' => "QL-{$year}-" . str_pad($num, 4, '0', STR_PAD_LEFT),
            'project_name' => $this->faker->sentence(5),
            'status' => 'in_progress',
            'proposal_value' => $this->faker->randomFloat(2, 100000, 10000000),
            'owner_id' => User::factory(),
            'due_date' => $this->faker->dateTimeBetween('+30 days', '+120 days')->format('Y-m-d'),
        ];
    }
}
