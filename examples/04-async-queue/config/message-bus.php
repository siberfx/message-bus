<?php

// Excerpt of config/message-bus.php

return [

    'transports' => [
        'sync' => ['driver' => 'sync'],

        // Default queue connection (QUEUE_CONNECTION), queue "messages".
        'async' => [
            'driver' => 'queue',
            'connection' => env('MESSAGE_BUS_QUEUE_CONNECTION'),
            'queue' => 'messages',
        ],

        // A dedicated Redis queue with its own retry policy and JSON payloads
        // (readable from Horizon / other languages).
        'newsletters' => [
            'driver' => 'queue',
            'connection' => 'redis',
            'queue' => 'newsletters',
            'serializer' => 'json',
            'retry' => [
                'max_retries' => 5,
                'delay' => 30_000,    // 30s, 60s, 120s, 240s, 480s
                'multiplier' => 2,
                'max_delay' => 600_000,
            ],
        ],

        'failed' => [
            'driver' => 'database',
            'queue' => 'failed',
        ],
    ],

    'routing' => [
        App\Messages\SendNewsletter::class => 'newsletters',

        // Everything implementing an interface:
        App\Contracts\ShouldRunAsync::class => 'async',

        // Handle inline AND keep a copy for an audit consumer:
        // App\Messages\Events\OrderPlaced::class => ['sync', 'audit'],
    ],

    'failure_transport' => 'failed',

];
