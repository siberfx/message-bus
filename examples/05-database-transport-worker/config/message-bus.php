<?php

// Excerpt of config/message-bus.php

return [

    'transports' => [
        'sync' => ['driver' => 'sync'],

        'priority' => [
            'driver' => 'database',
            'connection' => null,              // default DB connection
            'table' => 'message_bus_messages',
            'queue' => 'priority',
            'redeliver_timeout' => 600,        // seconds before an un-acked message is redelivered
        ],

        'default' => [
            'driver' => 'database',
            'table' => 'message_bus_messages',
            'queue' => 'default',
            'retry' => ['max_retries' => 5, 'delay' => 5000],
        ],

        'failed' => [
            'driver' => 'database',
            'table' => 'message_bus_messages',
            'queue' => 'failed',
        ],
    ],

    'routing' => [
        App\Messages\ResizeImage::class => 'priority',
        App\Messages\RebuildSearchIndex::class => 'default',
    ],

    'failure_transport' => 'failed',

    // Cache the attribute discovery in production (php artisan message-bus:cache)
    'discovery' => [
        'enabled' => true,
        'paths' => [app_path('Messages'), app_path('MessageHandlers')],
        'cache' => base_path('bootstrap/cache/message-bus.php'),
    ],

];
