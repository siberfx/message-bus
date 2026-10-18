<?php

namespace Siberfx\MessageBus\Realtime\Drivers;

use Siberfx\MessageBus\Contracts\RealtimeDriver;

class NullDriver implements RealtimeDriver
{
    public function publish(array $channels, string $event, array $payload = []): void
    {
    }
}
