<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Overseas Logistics API Configuration
    |--------------------------------------------------------------------------
    | New API uses OAuth2 client_credentials grant type
    | Base URL: https://api.overseaslogistic.com
    */

    'base_url'      => env('OVERSEAS_API_URL', 'https://api.overseaslogistic.com'),
    'client_id'     => env('OVERSEAS_CLIENT_ID'),
    'client_secret' => env('OVERSEAS_CLIENT_SECRET'),
    'account_code'  => env('OVERSEAS_ACCOUNT_CODE', 'PR4604'),

    /*
    |--------------------------------------------------------------------------
    | Token Cache
    |--------------------------------------------------------------------------
    | Token expires in 3600 seconds (1 hour)
    | We cache for 55 minutes to be safe
    */
    'token_cache_key' => 'overseas_api_token',
    'token_cache_ttl' => 55 * 60, // 55 minutes in seconds

];