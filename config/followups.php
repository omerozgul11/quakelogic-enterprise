<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Monthly status follow-up emails
    |--------------------------------------------------------------------------
    | When true, `follow-ups:monthly` actually emails the client contact for
    | each open proposal. Left OFF until a work mailbox (Google Workspace) is
    | connected so nothing goes to real recipients prematurely. The follow-up
    | thread records are still created regardless, so the Follow-Ups view is
    | populated either way.
    */
    'monthly_send_enabled' => env('FOLLOWUPS_MONTHLY_SEND', false),

    /*
    |--------------------------------------------------------------------------
    | Daily opportunity digest emails
    |--------------------------------------------------------------------------
    | When true, `inbox:opportunity-digest` emails each user a morning summary
    | of the opportunities matching their personal keywords. Left OFF until a
    | work mailbox is connected — the digest still lands in the in-app Inbox and
    | notifications regardless of this flag.
    */
    'digest_send_enabled' => env('FOLLOWUPS_DIGEST_SEND', false),
];
