<?php

// Excerpt of config/message-bus.php

use App\Messaging\Middleware\AuthorizeMiddleware;
use App\Messaging\Middleware\MetricsMiddleware;
use App\Messaging\Middleware\TenantContextMiddleware;
use Siberfx\MessageBus\Middleware\DatabaseTransactionMiddleware;
use Siberfx\MessageBus\Middleware\ValidationMiddleware;

return [

    'buses' => [
        'command.bus' => [
            'middleware' => [
                TenantContextMiddleware::class,      // 1. context first: everything below runs "inside" the tenant
                AuthorizeMiddleware::class,          // 2. reject early
                ValidationMiddleware::class,         // 3. validate payload
                MetricsMiddleware::class,            // 4. time sending/handling
                DatabaseTransactionMiddleware::class, // 5. wrap handlers in a transaction
            ],
            'allow_no_handlers' => false,
        ],

        'query.bus' => [
            'middleware' => [TenantContextMiddleware::class, MetricsMiddleware::class],
            'allow_no_handlers' => false,
        ],

        'event.bus' => [
            'middleware' => [TenantContextMiddleware::class],
            'allow_no_handlers' => true,
        ],
    ],

    // Built-in logging middleware (inserted right after PrepareEnvelope on every bus)
    'logging' => [
        'enabled' => env('MESSAGE_BUS_LOGGING', false),
        'channel' => 'daily',
        'level' => 'debug',
    ],

];
