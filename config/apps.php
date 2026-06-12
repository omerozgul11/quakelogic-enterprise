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
            'key' => 'project-manager',
            'name' => 'Project Manager',
            'description' => 'Plan & track projects',
            'icon' => 'folder-kanban',
            'url' => env('PROJECT_MANAGER_APP_URL', 'https://projectmanager.quakelogic.net'),
            'current' => false,
        ],
    ],

];
