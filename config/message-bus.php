<?php

use Siberfx\MessageBus\Middleware\ValidationMiddleware;

return [

    /*
    |--------------------------------------------------------------------------
    | Buses
    |--------------------------------------------------------------------------
    |
    | Every bus runs the same core pipeline:
    |
    |   prepare → [logging] → broadcast → <your middleware> → send → handle
    |
    | Add your own middleware (classes implementing MiddlewareInterface) per
    | bus. "allow_no_handlers" lets a bus accept messages nobody listens to,
    | which is what you want for domain events.
    |
    */

    'default_bus' => env('MESSAGE_BUS_DEFAULT', 'command.bus'),

    'default_query_bus' => 'query.bus',

    'buses' => [
        'command.bus' => [
            'middleware' => [
                ValidationMiddleware::class,
                // \Siberfx\MessageBus\Middleware\DatabaseTransactionMiddleware::class,
            ],
            'allow_no_handlers' => false,
        ],

        'query.bus' => [
            'middleware' => [],
            'allow_no_handlers' => false,
        ],

        'event.bus' => [
            'middleware' => [],
            'allow_no_handlers' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exceptions
    |--------------------------------------------------------------------------
    |
    | When a single handler fails during a synchronous dispatch, re-throw the
    | original exception (e.g. ValidationException, ModelNotFoundException)
    | instead of the HandlerFailedException wrapper.
    |
    */

    'unwrap_exceptions' => true,

    /*
    |--------------------------------------------------------------------------
    | Transports
    |--------------------------------------------------------------------------
    |
    | Drivers: "sync", "queue" (any Laravel queue connection: redis, sqs,
    | database, beanstalkd, ...), "database" (consumed by message-bus:consume),
    | "in-memory" (tests) or your own via MessageBus::extendTransport().
    |
    | Each transport may override "retry", "serializer" and "failure_transport".
    |
    */

    'transports' => [
        'sync' => ['driver' => 'sync'],

        'async' => [
            'driver' => 'queue',
            'connection' => env('MESSAGE_BUS_QUEUE_CONNECTION'),
            'queue' => env('MESSAGE_BUS_QUEUE', 'messages'),
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('MESSAGE_BUS_DB_CONNECTION'),
            'table' => 'message_bus_messages',
            'queue' => 'default',
            'redeliver_timeout' => 3600,
            'retry' => [
                'max_retries' => 3,
                'delay' => 1000,
            ],
        ],

        'failed' => [
            'driver' => 'database',
            'connection' => env('MESSAGE_BUS_DB_CONNECTION'),
            'table' => 'message_bus_messages',
            'queue' => 'failed',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    |
    | Map message classes (or parent classes / interfaces / "*") to transport
    | names. Unrouted messages are handled synchronously. You may also use
    | #[AsMessage(transport: 'async')] on the message class itself.
    |
    */

    'routing' => [
        // App\Messages\SendInvoice::class => 'async',
        // App\Contracts\AsyncMessage::class => ['async', 'audit'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Handlers
    |--------------------------------------------------------------------------
    |
    | Handlers are discovered from #[AsMessageHandler] attributes in the
    | discovery paths. You may also register them here:
    |
    |   App\Messages\PlaceOrder::class => App\MessageHandlers\PlaceOrderHandler::class,
    |   App\Events\OrderPlaced::class => [
    |       App\Listeners\SendReceipt::class,
    |       ['handler' => App\Listeners\NotifyWarehouse::class, 'bus' => 'event.bus', 'priority' => 10],
    |   ],
    |
    */

    'handlers' => [],

    'discovery' => [
        'enabled' => true,

        'paths' => [
            app_path('Messages'),
            app_path('MessageHandlers'),
        ],

        // Written by `php artisan message-bus:cache` (run it on deploy).
        'cache' => base_path('bootstrap/cache/message-bus.php'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retries & failures
    |--------------------------------------------------------------------------
    |
    | Default retry strategy for consumed messages. Delay is in milliseconds:
    | delay * multiplier ^ retries, capped at max_delay (0 = no cap).
    |
    */

    'retry' => [
        'max_retries' => 3,
        'delay' => 1000,
        'multiplier' => 2,
        'max_delay' => 60000,
    ],

    'failure_transport' => env('MESSAGE_BUS_FAILURE_TRANSPORT', 'failed'),

    /*
    |--------------------------------------------------------------------------
    | Serializer
    |--------------------------------------------------------------------------
    |
    | "php" (lossless, PHP only), "json" (readable by any language; messages
    | are rebuilt through their constructor) or a SerializerInterface class.
    |
    */

    'serializer' => env('MESSAGE_BUS_SERIALIZER', 'php'),

    /*
    |--------------------------------------------------------------------------
    | Realtime
    |--------------------------------------------------------------------------
    |
    | Broadcastable messages (#[BroadcastOn], ShouldBroadcastLive or a
    | BroadcastStamp) are pushed to these connections after being handled.
    |
    | "broadcast" uses Laravel broadcasting, so Reverb, Pusher, Ably, Redis
    | (Soketi) all work. "webhook" POSTs a signed payload to any service.
    |
    */

    'realtime' => [
        'enabled' => true,

        'default' => env('MESSAGE_BUS_REALTIME', 'broadcast'),

        // Throw when publishing fails instead of reporting the exception.
        'throw' => false,

        'connections' => [
            'broadcast' => [
                'driver' => 'broadcast',
                'connection' => env('BROADCAST_CONNECTION'),
            ],

            'reverb' => [
                'driver' => 'broadcast',
                'connection' => 'reverb',
            ],

            'pusher' => [
                'driver' => 'broadcast',
                'connection' => 'pusher',
            ],

            'ably' => [
                'driver' => 'broadcast',
                'connection' => 'ably',
            ],

            'webhook' => [
                'driver' => 'webhook',
                'url' => env('MESSAGE_BUS_WEBHOOK_URL'),
                'secret' => env('MESSAGE_BUS_WEBHOOK_SECRET'),
                'headers' => [],
                'timeout' => 5,
                'retries' => 2,
                'retry_delay' => 200,
            ],

            'log' => [
                'driver' => 'log',
                'channel' => env('LOG_CHANNEL'),
                'level' => 'info',
            ],

            'null' => [
                'driver' => 'null',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP endpoint (frontend dispatching)
    |--------------------------------------------------------------------------
    |
    | React, Vue, Blade/Alpine and plain JS clients dispatch messages by alias
    | through POST /{prefix}/dispatch. Only exposed messages are accepted:
    | #[AsMessage(alias: 'chat.send', exposed: true)] or the list below.
    |
    */

    'http' => [
        'enabled' => true,
        'prefix' => 'message-bus',
        'middleware' => ['web'],
        'throttle' => '120,1',
        'exposed' => [
            // 'chat.send' => App\Messages\SendChatMessage::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Browser client
    |--------------------------------------------------------------------------
    |
    | Used by @messageBusScripts / <x-message-bus::scripts />. When "echo" is
    | enabled and the page has no window.Echo yet, Pusher JS and Laravel Echo
    | are loaded from the CDN and configured for Reverb automatically.
    |
    */

    'client' => [
        'echo' => [
            'enabled' => env('MESSAGE_BUS_CLIENT_ECHO', false),
            'broadcaster' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'wsHost' => env('REVERB_HOST'),
            'wsPort' => env('REVERB_PORT', 80),
            'wssPort' => env('REVERB_PORT', 443),
            'forceTLS' => env('REVERB_SCHEME', 'https') === 'https',
            'scripts' => [
                'https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js',
                'https://cdn.jsdelivr.net/npm/laravel-echo@2.1.6/dist/echo.iife.js',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => env('MESSAGE_BUS_LOGGING', false),
        'channel' => env('MESSAGE_BUS_LOG_CHANNEL'),
        'level' => 'debug',
    ],

];
