<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Driver
    |--------------------------------------------------------------------------
    | Supported: "payu"
    |
    | Change PAYMENT_DRIVER in .env to swap gateway — zero code change.
    */
    'driver' => env('PAYMENT_DRIVER', 'payu'),

    /*
    |--------------------------------------------------------------------------
    | PayU Configuration
    |--------------------------------------------------------------------------
    | Get credentials from: https://onboarding.payu.in
    |
    | mode: test → use test.payu.in
    | mode: live → use secure.payu.in
    */
    'key'  => env('PAYU_KEY', ''),
    'salt' => env('PAYU_SALT', ''),
    'mode' => env('PAYU_MODE', 'test'),

    /*
    |--------------------------------------------------------------------------
    | PayU URLs
    |--------------------------------------------------------------------------
    */
    'test_url' => 'https://test.payu.in/_payment',
    'live_url' => 'https://secure.payu.in/_payment',

];