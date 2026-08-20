<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Status check history retention
    |--------------------------------------------------------------------------
    */

    'keep_last_per_admin' => (int) env('DOMAIN_STATUS_CHECK_KEEP_LAST', 5),

    'retention_days' => (int) env('DOMAIN_STATUS_CHECK_RETENTION_DAYS', 7),

    'stale_hours' => (int) env('DOMAIN_STATUS_CHECK_STALE_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Safe batch deletion (scalability)
    |--------------------------------------------------------------------------
    |
    | Deletes run in small chunks with a per-run cap so pruning never locks
    | the database or blocks HTTP requests for long periods.
    |
    */

    'delete_chunk_size' => (int) env('DOMAIN_STATUS_CHECK_DELETE_CHUNK', 25),

    /** Max parent check rows deleted per job / scheduled run. */
    'max_deletes_per_run' => (int) env('DOMAIN_STATUS_CHECK_MAX_DELETES_PER_RUN', 100),

    /** Max admins processed when trimming all histories in one scheduled run. */
    'max_admins_per_run' => (int) env('DOMAIN_STATUS_CHECK_MAX_ADMINS_PER_RUN', 20),

    /*
    |--------------------------------------------------------------------------
    | HTTP checks (WordPress /wp-json/external/v1/status)
    |--------------------------------------------------------------------------
    |
    | request_timeout: total wait per domain (slow sites may need 60–90s).
    | connect_timeout: TCP/TLS handshake only — increase if DNS/SSL is slow.
    | chunk_size: domains checked in parallel per queue job (lower = gentler).
    | job_timeout: must exceed request_timeout + buffer (see job class).
    |
    */

    'chunk_size' => (int) env('DOMAIN_STATUS_CHECK_CHUNK_SIZE', 8),

    'request_timeout' => (int) env('DOMAIN_STATUS_CHECK_REQUEST_TIMEOUT', 60),

    'connect_timeout' => (int) env('DOMAIN_STATUS_CHECK_CONNECT_TIMEOUT', 20),

    'retry_request_timeout' => (int) env('DOMAIN_STATUS_CHECK_RETRY_REQUEST_TIMEOUT', 90),

    'retry_connect_timeout' => (int) env('DOMAIN_STATUS_CHECK_RETRY_CONNECT_TIMEOUT', 25),

    /** Queue job max seconds (one parallel batch). */
    'job_timeout' => (int) env('DOMAIN_STATUS_CHECK_JOB_TIMEOUT', 180),

    'lock_seconds' => (int) env('DOMAIN_STATUS_CHECK_LOCK_SECONDS', 240),

    /*
    |--------------------------------------------------------------------------
    | Scheduled inventory health sync queue
    |--------------------------------------------------------------------------
    |
    | Separated from domainCheck so the Status Checker UI is not blocked behind
    | hundreds of scheduled RefreshTransferredDomainsStatusJob jobs.
    |
    */

    'health_sync_queue' => env('DOMAIN_HEALTH_SYNC_QUEUE', 'domainHealthSync'),

    /*
    |--------------------------------------------------------------------------
    | Recheck disconnected domains (inventory status = 0)
    |--------------------------------------------------------------------------
    */

    'disconnected_max' => (int) env('DOMAIN_STATUS_CHECK_DISCONNECTED_MAX', 10000),

    /*
    |--------------------------------------------------------------------------
    | Per-domain probe attempts (campaign-style backoff)
    |--------------------------------------------------------------------------
    |
    | After each failed attempt (except the last), wait the matching delay
    | before retrying. Example with defaults: attempt 1 immediate, then wait
    | 1m / 2m / 4m / 5m between failures; 5th failure → disconnected.
    |
    */

    'max_attempts' => (int) env('DOMAIN_STATUS_CHECK_MAX_ATTEMPTS', 5),

    'backoff_seconds' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('DOMAIN_STATUS_CHECK_BACKOFF_SECONDS', '60,120,240,300'))
    ), fn ($v) => $v > 0)),

];
