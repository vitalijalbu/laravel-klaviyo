<?php

return [
    'api_key' => env('KLAVIYO_API_KEY'),
    'api_url' => env('KLAVIYO_API_URL', 'https://a.klaviyo.com/api'),
    'api_version' => '2024-10-15',

    'queue' => [
        'connection' => env('KLAVIYO_QUEUE_CONNECTION', 'redis'),
        'name' => env('KLAVIYO_QUEUE_NAME', 'klaviyo'),
    ],
];
