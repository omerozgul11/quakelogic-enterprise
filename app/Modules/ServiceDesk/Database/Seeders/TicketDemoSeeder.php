<?php

namespace App\Modules\ServiceDesk\Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Modules\ServiceDesk\Models\Ticket;
use App\Modules\ServiceDesk\Services\TicketService;
use Illuminate\Database\Seeder;

/**
 * Optional demo data for Service Desk — a handful of tickets across types. NOT
 * wired into DatabaseSeeder. Invoke explicitly:
 *
 *   php artisan db:seed --class="App\Modules\ServiceDesk\Database\Seeders\TicketDemoSeeder"
 */
class TicketDemoSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->orderBy('id')->first();
        $user = $org ? User::where('organization_id', $org->id)->orderBy('id')->first() : null;

        if (! $org || ! $user) {
            $this->command?->warn('TicketDemoSeeder: no organization/user found — skipping.');

            return;
        }

        if (Ticket::where('organization_id', $org->id)->exists()) {
            $this->command?->info('TicketDemoSeeder: tickets already exist — skipping.');

            return;
        }

        $service = app(TicketService::class);
        $samples = [
            ['subject' => 'F330 sensor not reporting after firmware update', 'type' => 'support', 'priority' => 'high'],
            ['subject' => 'Annual service visit — Berkeley shake table', 'type' => 'field_service', 'priority' => 'normal'],
            ['subject' => 'RMA: CUBE digitizer DOA on arrival', 'type' => 'rma', 'priority' => 'urgent', 'rma_disposition' => 'replace', 'serial_number' => 'SN-22841'],
            ['subject' => 'Question about GPS antenna mounting', 'type' => 'support', 'priority' => 'low'],
        ];

        foreach ($samples as $s) {
            $ticket = $service->open($org->id, $user->id, $s + ['channel' => 'email']);
            $service->comment($ticket, $user->id, 'Thanks for reaching out — taking a look now.', false);
        }

        $this->command?->info('TicketDemoSeeder: created '.count($samples)." tickets for \"{$org->name}\".");
    }
}
