<?php

return [

    'max_domains_per_deployment' => (int) env('PLUGIN_MANAGER_MAX_DOMAINS', 500),

    /*
     * The WordPress agent accepts ZIP downloads up to 50 MB. The dashboard
     * upload ceiling may be lowered independently, while the legacy
     * PLUGIN_MANAGER_MAX_ZIP_MB variable remains supported by both settings.
     */
    'agent_max_zip_mb' => (int) env(
        'PLUGIN_MANAGER_AGENT_MAX_ZIP_MB',
        env('PLUGIN_MANAGER_MAX_ZIP_MB', 50)
    ),

    'dashboard_max_zip_mb' => (int) env(
        'PLUGIN_MANAGER_DASHBOARD_MAX_ZIP_MB',
        env('PLUGIN_MANAGER_MAX_ZIP_MB', 50)
    ),

    // Backward-compatible key used by existing upload code.
    'max_zip_mb' => (int) env(
        'PLUGIN_MANAGER_DASHBOARD_MAX_ZIP_MB',
        env('PLUGIN_MANAGER_MAX_ZIP_MB', 50)
    ),

    'max_zip_bytes' => (int) env(
        'PLUGIN_MANAGER_AGENT_MAX_ZIP_BYTES',
        (int) env('PLUGIN_MANAGER_AGENT_MAX_ZIP_MB', env('PLUGIN_MANAGER_MAX_ZIP_MB', 50)) * 1024 * 1024
    ),

    'storage_disk' => env('PLUGIN_MANAGER_STORAGE_DISK', 'local'),

    'chunk_size' => (int) env('PLUGIN_MANAGER_CHUNK_SIZE', 2),

    'request_timeout' => (int) env('PLUGIN_MANAGER_REQUEST_TIMEOUT', 180),

    'connect_timeout' => (int) env('PLUGIN_MANAGER_CONNECT_TIMEOUT', 20),

    'status_timeout' => (int) env('PLUGIN_MANAGER_STATUS_TIMEOUT', 15),

    'status_connect_timeout' => (int) env('PLUGIN_MANAGER_STATUS_CONNECT_TIMEOUT', 10),

    'insecure_tls' => filter_var(env('PLUGIN_MANAGER_INSECURE_TLS', false), FILTER_VALIDATE_BOOL),

    'download_timeout' => (int) env('PLUGIN_MANAGER_DOWNLOAD_TIMEOUT', 300),

    'job_timeout' => (int) env('PLUGIN_MANAGER_JOB_TIMEOUT', 400),

    'lock_seconds' => (int) env('PLUGIN_MANAGER_LOCK_SECONDS', 420),

    'diamond_pbn_slug' => 'external-api-manager',

    'download_url_ttl_hours' => (int) env('PLUGIN_MANAGER_DOWNLOAD_TTL_HOURS', 24),

    'signing_key' => env('PLUGIN_MANAGER_SIGNING_KEY'),

    'keep_last_per_admin' => (int) env('PLUGIN_MANAGER_KEEP_LAST', 10),

    'retention_days' => (int) env('PLUGIN_MANAGER_RETENTION_DAYS', 30),

    'require_inventory' => filter_var(env('PLUGIN_MANAGER_REQUIRE_INVENTORY', true), FILTER_VALIDATE_BOOL),

    // Backward-compatible name used by earlier dashboard builds.
    'require_inventory_match' => filter_var(env('PLUGIN_MANAGER_REQUIRE_INVENTORY', true), FILTER_VALIDATE_BOOL),

    'http_retry_attempts' => (int) env('PLUGIN_MANAGER_HTTP_RETRY_ATTEMPTS', 2),

    'http_retry_max_delay_ms' => (int) env('PLUGIN_MANAGER_HTTP_RETRY_MAX_DELAY_MS', 1000),

    'require_connected_domain' => (bool) env('PLUGIN_MANAGER_REQUIRE_CONNECTED', true),

    'min_agent_version' => env('PLUGIN_MANAGER_MIN_AGENT_VERSION', '8.1.5'),

];
