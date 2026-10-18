<?php

// Excerpt of config/message-bus.php

return [

    'realtime' => [
        'enabled' => true,
        'default' => env('MESSAGE_BUS_REALTIME', 'pusher'),
        'throw' => false,

        'connections' => [
            // Laravel broadcasting connections (config/broadcasting.php)
            'pusher' => ['driver' => 'broadcast', 'connection' => 'pusher'],
            'ably' => ['driver' => 'broadcast', 'connection' => 'ably'],
            'reverb' => ['driver' => 'broadcast', 'connection' => 'reverb'],
            'soketi' => ['driver' => 'broadcast', 'connection' => 'pusher-soketi'], // a second pusher connection pointing to Soketi

            // Any HTTP endpoint
            'webhook' => [
                'driver' => 'webhook',
                'url' => env('MESSAGE_BUS_WEBHOOK_URL', 'http://localhost:6002/hook'),
                'secret' => env('MESSAGE_BUS_WEBHOOK_SECRET'),
                'headers' => ['X-App' => env('APP_NAME')],
                'timeout' => 5,
                'retries' => 3,
                'retry_delay' => 200,
            ],

            // Custom drivers registered in RealtimeServiceProvider
            'centrifugo' => [
                'driver' => 'centrifugo',
                'url' => env('CENTRIFUGO_API_URL', 'http://localhost:8000/api'),
                'key' => env('CENTRIFUGO_API_KEY'),
            ],

            'mercure' => [
                'driver' => 'mercure',
                'hub' => env('MERCURE_HUB_URL', 'http://localhost:3000/.well-known/mercure'),
                'jwt' => env('MERCURE_PUBLISHER_JWT'),
                'topic_prefix' => env('APP_URL').'/',
            ],

            'log' => ['driver' => 'log', 'level' => 'debug'],
            'null' => ['driver' => 'null'],
        ],
    ],

];
