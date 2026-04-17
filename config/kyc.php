<?php

return [

    /*
    |--------------------------------------------------------------------------
    | KYC Verification Driver
    |--------------------------------------------------------------------------
    | Supported: "manual", "surepass"
    |
    | manual   → admin reviews and approves manually (current)
    | surepass → auto-verified via Surepass API (future)
    |
    | Change KYC_DRIVER in .env to swap — zero code change.
    */
    'driver' => env('KYC_DRIVER', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | Surepass API Configuration
    |--------------------------------------------------------------------------
    | Get your token from: https://surepass.io
    |
    | Supports:
    |   - PAN verification (individual + company)
    |   - GST verification (company)
    |   - Aadhaar verification via OTP (individual)
    */
    'surepass' => [
        'api_url' => env('SUREPASS_API_URL', 'https://kyc-api.surepass.io/api/v1'),
        'token'   => env('SUREPASS_TOKEN', ''),
    ],

];