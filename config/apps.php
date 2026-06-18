<?php

return [

    /*
    |--------------------------------------------------------------------------
    | App Switcher Registry
    |--------------------------------------------------------------------------
    |
    | Powers the top-left logo launcher — the hub for every QuakeLogic product.
    | Entries whose 'url' is a relative path (starts with '/') are SECTIONS of
    | this same app, linked internally via Inertia; the active one is detected
    | from the current path. Entries with an absolute URL are separate apps.
    |
    */

    'switcher' => [
        [
            'key' => 'proposals',
            'name' => 'Proposals',
            'description' => 'Bid intelligence & proposals',
            'icon' => 'file-text',
            'url' => '/',
            'current' => true,
        ],
        [
            'key' => 'shipments',
            'name' => 'Shipments',
            'description' => 'UPS tracking for mailed proposals',
            'icon' => 'truck',
            // Now a section of this app (not a separate site): /shipments.
            'url' => '/shipments',
            'current' => false,
        ],
        [
            'key' => 'crm',
            'name' => 'CRM',
            'description' => 'Clients, leads, projects & invoices',
            'icon' => 'contact-round',
            // A section of this app (not a separate site): /crm.
            'url' => '/crm',
            'current' => false,
        ],
        [
            'key' => 'inventory',
            'name' => 'Inventory',
            'description' => 'Product master, stock & warehouses',
            'icon' => 'boxes',
            // A section of this app (not a separate site): /inventory.
            'url' => '/inventory',
            'current' => false,
        ],
        [
            'key' => 'procurement',
            'name' => 'Procurement',
            'description' => 'Suppliers & purchase orders',
            'icon' => 'shopping-cart',
            // A section of this app (not a separate site): /procurement.
            'url' => '/procurement',
            'current' => false,
        ],
        [
            'key' => 'manufacturing',
            'name' => 'Manufacturing',
            'description' => 'BOMs & work orders',
            'icon' => 'factory',
            // A section of this app (not a separate site): /manufacturing.
            'url' => '/manufacturing',
            'current' => false,
        ],
        [
            'key' => 'assets',
            'name' => 'Assets',
            'description' => 'Instrument registry & maintenance',
            'icon' => 'cpu',
            // A section of this app (not a separate site): /assets.
            'url' => '/assets',
            'current' => false,
        ],
        [
            'key' => 'calibration',
            'name' => 'Calibration',
            'description' => 'NIST-traceable certificates & due dates',
            'icon' => 'badge-check',
            // A section of this app (not a separate site): /calibration.
            'url' => '/calibration',
            'current' => false,
        ],
        [
            'key' => 'tickets',
            'name' => 'Service Desk',
            'description' => 'Support, service & RMA tickets',
            'icon' => 'life-buoy',
            // A section of this app (not a separate site): /tickets.
            'url' => '/tickets',
            'current' => false,
        ],
        [
            'key' => 'finance',
            'name' => 'Finance',
            'description' => 'Receivables, payments & credit notes',
            'icon' => 'landmark',
            // A section of this app (not a separate site): /finance.
            'url' => '/finance',
            'current' => false,
        ],
    ],

];
