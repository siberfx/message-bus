<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Handlers;

use RuntimeException;
use Siberfx\MessageBus\Attribute\AsMessageHandler;
use Siberfx\MessageBus\Tests\Fixtures\Messages\CreateUser;
use Siberfx\MessageBus\Tests\Fixtures\Messages\GetGreeting;
use Siberfx\MessageBus\Tests\Fixtures\Recorder;

/**
 * Method level attributes: one class handling several messages.
 */
final class UserHandlers
{
    #[AsMessageHandler]
    public function create(CreateUser $message): array
    {
        if ($message->name === 'explode') {
            throw new RuntimeException('Boom');
        }

        Recorder::record('create:'.$message->name);

        return ['id' => 1, 'name' => $message->name, 'status' => $message->status];
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function greet(GetGreeting $query): string
    {
        return 'Hello '.$query->name;
    }
}
