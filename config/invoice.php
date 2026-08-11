<?php

return [
    'company_name' => env('INVOICE_COMPANY_NAME', 'Diamond PBN'),
    'company_address' => env('INVOICE_COMPANY_ADDRESS', ''),
    'company_email' => env('INVOICE_COMPANY_EMAIL', ''),
    'company_phone' => env('INVOICE_COMPANY_PHONE', ''),
    'logo_path' => env('INVOICE_LOGO_PATH', 'invoice-logo.png'),
    'default_currency' => env('INVOICE_DEFAULT_CURRENCY', 'USD'),
    'theme_color' => env('INVOICE_THEME_COLOR', '#4f79bd'),
];
