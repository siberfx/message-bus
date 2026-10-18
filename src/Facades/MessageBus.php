<?php

namespace Siberfx\MessageBus\Facades;

use Illuminate\Support\Facades\Facade;
use Siberfx\MessageBus\MessageBusManager;

/**
 * @method static \Siberfx\MessageBus\Contracts\MessageBusInterface bus(?string $name = null)
 * @method static \Siberfx\MessageBus\Contracts\MessageBusInterface realBus(?string $name = null)
 * @method static \Siberfx\MessageBus\Envelope dispatch(object $message, array $stamps = [], ?string $bus = null)
 * @method static mixed ask(object $query, array $stamps = [], ?string $bus = null)
 * @method static void publish(string|array $channels, string $event, array $payload = [], ?string $connection = null)
 * @method static \Siberfx\MessageBus\MessageBusManager handle(string $messageType, mixed $handler, array $options = [])
 * @method static \Siberfx\MessageBus\MessageBusManager route(string $messageType, string|array $transports)
 * @method static \Siberfx\MessageBus\MessageBusManager expose(string $alias, string $messageClass)
 * @method static \Siberfx\MessageBus\Contracts\TransportInterface transport(string $name)
 * @method static \Siberfx\MessageBus\Contracts\RealtimeDriver realtime(?string $connection = null)
 * @method static \Siberfx\MessageBus\MessageBusManager extendTransport(string $driver, \Closure $callback)
 * @method static \Siberfx\MessageBus\MessageBusManager extendRealtime(string $driver, \Closure $callback)
 * @method static \Siberfx\MessageBus\Testing\MessageBusFake fake(array $only = [])
 * @method static \Siberfx\MessageBus\Testing\RealtimeFake fakeRealtime()
 * @method static bool isFake()
 * @method static string defaultBus()
 * @method static \Siberfx\MessageBus\Handler\HandlersLocator handlers()
 * @method static \Siberfx\MessageBus\Transport\TransportManager transports()
 * @method static \Siberfx\MessageBus\Support\MessageRegistry registry()
 *
 * @see MessageBusManager
 */
class MessageBus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MessageBusManager::class;
    }
}
