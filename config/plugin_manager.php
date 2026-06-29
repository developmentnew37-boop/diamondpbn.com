<?php

return [

    'max_domains_per_deployment' => (int) env('PLUGIN_MANAGER_MAX_DOMAINS', 500),

    'max_zip_mb' => (int) env('PLUGIN_MANAGER_MAX_ZIP_MB', 5),

    'storage_disk' => env('PLUGIN_MANAGER_STORAGE_DISK', 'local'),

    'chunk_size' => (int) env('PLUGIN_MANAGER_CHUNK_SIZE', 2),

    'request_timeout' => (int) env('PLUGIN_MANAGER_REQUEST_TIMEOUT', 180),

    'connect_timeout' => (int) env('PLUGIN_MANAGER_CONNECT_TIMEOUT', 20),

    'job_timeout' => (int) env('PLUGIN_MANAGER_JOB_TIMEOUT', 400),

    'lock_seconds' => (int) env('PLUGIN_MANAGER_LOCK_SECONDS', 420),

    'diamond_pbn_slug' => 'external-api-manager',

    'download_url_ttl_hours' => (int) env('PLUGIN_MANAGER_DOWNLOAD_TTL_HOURS', 24),

    'signing_key' => env('PLUGIN_MANAGER_SIGNING_KEY'),

    'keep_last_per_admin' => (int) env('PLUGIN_MANAGER_KEEP_LAST', 10),

    'retention_days' => (int) env('PLUGIN_MANAGER_RETENTION_DAYS', 30),

    'require_inventory_match' => (bool) env('PLUGIN_MANAGER_REQUIRE_INVENTORY', true),

    'require_connected_domain' => (bool) env('PLUGIN_MANAGER_REQUIRE_CONNECTED', true),

];
