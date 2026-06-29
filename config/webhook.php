<?php

return [

    /*
    | Default auto-rotation interval (hours) for new installs / empty DB row.
    | 0 = disabled. Admin can override from Webhook Secrets page.
    | Env fallback only — live value is stored in webhook_rotation_settings.
    */
    'default_rotation_hours' => (int) env('WEBHOOK_SECRET_ROTATION_HOURS', 8),

    /*
    | Maximum allowed rotation interval from admin UI (hours).
    */
    'max_rotation_hours' => (int) env('WEBHOOK_SECRET_ROTATION_MAX_HOURS', 168),

    'rotation_queue' => env('WEBHOOK_SECRET_ROTATION_QUEUE', 'webhook-secret'),

];
