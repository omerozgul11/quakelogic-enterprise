<?php

namespace App\Services\Ups\QuantumView;

use Illuminate\Support\Carbon;

interface QuantumViewClient
{
    /**
     * Fetch account shipment activity since a point in time.
     *
     * @return QuantumViewActivity[]
     *
     * @throws \App\Services\Tracking\TrackingException
     */
    public function fetch(Carbon $since): array;
}
