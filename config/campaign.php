<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Campaign Pagination Settings
    |--------------------------------------------------------------------------
    |
    | Default pagination limit for campaign listing pages across all campaign types.
    | This ensures consistent user experience and predictable performance.
    |
    */

    'pagination' => [
        'default_limit' => env('CAMPAIGN_PAGINATION_LIMIT', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Processing Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for background job processing including retry logic,
    | locking mechanisms, and backoff strategies.
    |
    */

    'jobs' => [
        // Number of times Laravel's queue system should retry a failed job
        // Set to 1 because we implement custom retry logic inside jobs
        'max_tries' => 1,

        // Maximum number of retry attempts within job's internal retry logic
        'max_internal_retries' => 5,

        // Base backoff time in seconds for exponential backoff (1m, 2m, 4m, 8m...)
        'base_backoff_seconds' => 60,

        // Lock TTL in seconds - how long a job can hold a lock before it expires
        'lock_ttl_seconds' => 180, // 3 minutes

        // Maximum time for exponential backoff
        'max_backoff_seconds' => 3600, // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for HTTP requests to remote WordPress sites.
    |
    */

    'http' => [
        // Default timeout for HTTP requests to WordPress APIs
        'timeout_seconds' => 60,

        // Whether to verify SSL certificates (should be true in production)
        'verify_ssl' => env('CAMPAIGN_VERIFY_SSL', false),

        // Connection timeout
        'connect_timeout_seconds' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Campaign Status Values
    |--------------------------------------------------------------------------
    |
    | Valid status values for campaigns and their tasks/posts.
    |
    */

    'statuses' => [
        'campaign' => [
            'queued' => 'queued',
            'running' => 'running',
            'completed' => 'completed',
            'semi_failed' => 'semi_failed',
            'failed' => 'failed',
            'paused' => 'paused',
            'cancelled' => 'cancelled',
        ],

        'task' => [
            'queued' => 'queued',
            'publishing' => 'publishing',
            'success' => 'success',
            'failed' => 'failed',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Campaign Report Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for campaign reports and exports.
    |
    */

    'reports' => [
        // Length of the random token for public report access
        'token_length' => 64,

        // Export formats available
        'export_formats' => ['excel', 'csv'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Article Settings
    |--------------------------------------------------------------------------
    |
    | Configuration related to article management and usage.
    |
    */

    'articles' => [
        // Whether to soft-delete articles after successful campaign publication
        'soft_delete_after_use' => true,

        // Maximum article title length
        'max_title_length' => 255,

        // Maximum article body length
        'max_body_length' => 65535, // TEXT column limit
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for domain management and health checks.
    |
    */

    'domains' => [
        // Default domain status check timeout
        'status_check_timeout' => 30,

        // Whether API keys should be encrypted in database (future enhancement)
        'encrypt_api_keys' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Common validation rules used across campaign controllers.
    |
    */

    'validation' => [
        'campaign_no_max_length' => 150,
        'search_max_length' => 150,
        'filter_user_max_length' => 20,
        'keyword_max_length' => 255,
        'url_max_length' => 2048,
    ],

];
