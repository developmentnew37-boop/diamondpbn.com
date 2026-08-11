<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Credential blind-index key
    |--------------------------------------------------------------------------
    |
    | Use a dedicated, stable random key in production. APP_KEY is accepted as
    | a compatibility fallback so existing installations can deploy the schema
    | before provisioning the dedicated key.
    |
    */
    'lookup_key' => env('CREDENTIAL_LOOKUP_KEY', env('APP_KEY')),
];
