<?php

return [

    'base_url'        => 'https://apiv2.shiprocket.in/v1/external',
    'email'           => env('SHIPROCKET_EMAIL'),
    'password'        => env('SHIPROCKET_PASSWORD'),
    'pickup_location' => env('SHIPROCKET_PICKUP_LOCATION', 'Primary'),
    'pickup_pincode'  => env('SHIPROCKET_PICKUP_PINCODE'),

    /*
    |--------------------------------------------------------------------------
    | Token Cache
    |--------------------------------------------------------------------------
    | Shiprocket token is valid for 10 days.
    | We cache for 9 days (777600 seconds) to be safe.
    */
    'token_cache_key' => 'shiprocket_api_token',
    'token_cache_ttl' => 9 * 24 * 60 * 60, // 9 days

    /*
    |--------------------------------------------------------------------------
    | Shiprocket-routed Carriers
    |--------------------------------------------------------------------------
    | Bookings for these carriers are sent to Shiprocket instead of Overseas.
    | Also, any booking with network = "eCommerce" goes to Shiprocket.
    */
    /*
    |--------------------------------------------------------------------------
    | Only these two routes go to Shiprocket:
    | 1. carrier = 'India Post'  (by carrier name)
    | 2. network = 'eCommerce'   (handled in resolvePlatform — not here)
    |--------------------------------------------------------------------------
    */
    'carriers' => [
        'India Post',
    ],

];
