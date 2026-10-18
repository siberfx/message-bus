<?php

// Excerpt of config/message-bus.php

return [

    // Default for every transport: 3 retries after 1s, 2s, 4s (capped at 60s).
    'retry' => [
        'max_retries' => 3,
        'delay' => 1000,
        'multiplier' => 2,
        'max_delay' => 60_000,
    ],

    'failure_transport' => 'failed',

    'transports' => [
        'async' => [
            'driver' => 'queue',
            'queue' => 'messages',
        ],

        // Payment provider hiccups: retry often, but not forever.
        'payments' => [
            'driver' => 'queue',
            'queue' => 'payments',
            'retry' => [
                'max_retries' => 8,
                'delay' => 10_000,
                'multiplier' => 1.5,
                'max_delay' => 300_000,
            ],
        ],

        // Your own strategy class (resolved from the container, receives the merged config).
        'crm' => [
            'driver' => 'database',
            'queue' => 'crm',
            'retry' => [
                'strategy' => App\Retry\BusinessHoursRetryStrategy::class,
                'max_retries' => 10,
            ],
        ],

        // Fire and forget: no retries, no failure transport.
        'analytics' => [
            'driver' => 'queue',
            'queue' => 'analytics',
            'retry' => ['max_retries' => 0],
            'failure_transport' => null,
        ],

        'failed' => [
            'driver' => 'database',
            'queue' => 'failed',
        ],
    ],

    'routing' => [
        App\Messages\ChargeCustomer::class => 'payments',
        App\Messages\SyncCrmContact::class => 'crm',
        App\Messages\TrackEvent::class => 'analytics',
    ],

];
