<?php

return [
    /*
     | The vendor self-service portal (suppliers logging in to view their own
     | POs, quotations, and bills). Off by default — it exposes an externally
     | reachable login, so enable only after a security review.
     */
    'vendor_portal_enabled' => env('VENDOR_PORTAL_ENABLED', false),
];
