<?php

return [
    'api_url'      => env('DING_API_URL', 'https://api.dingconnect.com'),
    'api_key'      => env('DING_API_KEY'),
    'api_secret'   => env('DING_API_SECRET'),
    'customer_id'  => env('DING_CUSTOMER_ID'),
    'default_agent' => env('DING_DEFAULT_AGENT'),
];
