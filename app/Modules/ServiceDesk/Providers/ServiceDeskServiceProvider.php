<?php

namespace App\Modules\ServiceDesk\Providers;

use App\Modules\ModuleServiceProvider;
use App\Modules\ServiceDesk\Models\Ticket;
use App\Modules\ServiceDesk\Policies\TicketPolicy;

class ServiceDeskServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }

    /** @return array<class-string,class-string> */
    protected function policies(): array
    {
        return [
            Ticket::class => TicketPolicy::class,
        ];
    }
}
