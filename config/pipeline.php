<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automatic pipeline refresh (on login)
    |--------------------------------------------------------------------------
    |
    | On every login the pipeline is refreshed: past-due opportunities are
    | removed, and fresh opportunities are pulled from SAM.gov. The SAM.gov
    | pull is throttled so frequent logins don't hammer the external API —
    | within the throttle window only the expired-purge runs (which is cheap).
    */

    'sync_throttle_minutes' => (int) env('PIPELINE_SYNC_THROTTLE_MINUTES', 15),

    'sync_max_pages' => (int) env('PIPELINE_SYNC_MAX_PAGES', 2),

    /*
    |--------------------------------------------------------------------------
    | Keyword-targeted SAM.gov pulls
    |--------------------------------------------------------------------------
    |
    | Personal keywords also steer the SAM.gov sync: each sync runs a targeted
    | title search per keyword (capped at keyword_sync_max across the team) and
    | looks back keyword_lookback_days, since keyword matches are sparse in the
    | recency feed. Past-due notices are purged immediately after import.
    */

    'keyword_sync_max' => (int) env('PIPELINE_KEYWORD_SYNC_MAX', 8),

    'keyword_lookback_days' => (int) env('PIPELINE_KEYWORD_LOOKBACK_DAYS', 120),

    /*
    |--------------------------------------------------------------------------
    | Keyword filter chips
    |--------------------------------------------------------------------------
    |
    | Selectable keywords shown on the Opportunities page. Selecting one or
    | more narrows the list to opportunities mentioning any of them (title,
    | description, scope, agency, etc.). Tune this list to your focus areas.
    */

    'keywords' => array_values(array_filter(array_map('trim', explode(
        ',',
        env(
            'PIPELINE_KEYWORDS',
            'services, maintenance, repair, construction, installation, engineering, '
            . 'support, system, equipment, security, monitoring, inspection, '
            . 'training, environmental'
        )
    )))),

];
