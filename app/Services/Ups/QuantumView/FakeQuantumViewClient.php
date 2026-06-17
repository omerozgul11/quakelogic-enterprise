<?php

namespace App\Services\Ups\QuantumView;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Simulates a Quantum View account feed so the auto-ingest pipeline is testable
 * before the real Quantum View product is enabled (mirrors the fake-when-disabled
 * convention). Returns a couple of made-up "account shipments" each run.
 */
class FakeQuantumViewClient implements QuantumViewClient
{
    public function fetch(Carbon $since): array
    {
        $now = Carbon::now();
        $tn1 = '1Z'.strtoupper(Str::random(16));
        $tn2 = '1Z'.strtoupper(Str::random(16));

        return [
            new QuantumViewActivity(
                trackingNumber: $tn1,
                type: 'manifest',
                scheduledDelivery: $now->copy()->addDays(2),
                occurredAt: $now->copy()->subHours(1),
                description: 'Shipment manifest received by UPS',
                location: 'Louisville, KY, US',
                recipientName: 'Department of Example — Contracting Office',
                references: [],
            ),
            new QuantumViewActivity(
                trackingNumber: $tn2,
                type: 'delivery',
                scheduledDelivery: $now->copy()->subDay(),
                occurredAt: $now->copy()->subHours(5),
                description: 'Delivered',
                location: 'Washington, DC, US',
                recipientName: 'General Services Administration',
                references: [],
            ),
        ];
    }
}
