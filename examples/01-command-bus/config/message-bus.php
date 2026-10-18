<?php

// Excerpt of config/message-bus.php — only the keys relevant to this example.

use Siberfx\MessageBus\Middleware\DatabaseTransactionMiddleware;
use Siberfx\MessageBus\Middleware\ValidationMiddleware;

return [

    'default_bus' => 'command.bus',

    'buses' => [
        'command.bus' => [
            'middleware' => [
                ValidationMiddleware::class,          // validate first (cheap, no transaction needed)
                DatabaseTransactionMiddleware::class, // then handle inside a DB transaction
            ],
            'allow_no_handlers' => false,
        ],

        'query.bus' => ['middleware' => [], 'allow_no_handlers' => false],
        'event.bus' => ['middleware' => [], 'allow_no_handlers' => true],
    ],

];
