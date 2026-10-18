<?php

namespace Siberfx\MessageBus\Realtime;

use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Siberfx\MessageBus\Contracts\RealtimeDriver;
use Siberfx\MessageBus\Realtime\Drivers\BroadcastDriver;
use Siberfx\MessageBus\Realtime\Drivers\LogDriver;
use Siberfx\MessageBus\Realtime\Drivers\NullDriver;
use Siberfx\MessageBus\Realtime\Drivers\WebhookDriver;
use Siberfx\MessageBus\Testing\RealtimeFake;

/**
 * Resolves the realtime connections defined in config("message-bus.realtime").
 *
 * Built-in drivers:
 *  - broadcast: any Laravel broadcasting connection (reverb, pusher, ably, redis, log)
 *  - webhook:   POSTs a signed JSON payload to any external service
 *  - log / null
 */
class RealtimeManager
{
    /**
     * @var array<string, RealtimeDriver>
     */
    protected array $connections = [];

    /**
     * @var array<string, Closure(array, Container): RealtimeDriver>
     */
    protected array $customCreators = [];

    protected ?RealtimeFake $fake = null;

    public function __construct(protected Container $app)
    {
    }

    public function connection(?string $name = null): RealtimeDriver
    {
        if ($this->fake !== null) {
            return $this->fake;
        }

        $name ??= $this->getDefaultConnection();

        return $this->connections[$name] ??= $this->resolve($name);
    }

    /**
     * @param  string|list<string>  $channels
     * @param  array<string, mixed>  $payload
     */
    public function publish(string|array $channels, string $event, array $payload = [], ?string $connection = null): void
    {
        $this->connection($connection)->publish(array_values((array) $channels), $event, $payload);
    }

    public function getDefaultConnection(): string
    {
        return (string) $this->app['config']->get('message-bus.realtime.default', 'null');
    }

    /**
     * Register a custom realtime driver:
     *
     *     MessageBus::extendRealtime('mercure', fn (array $config, $app) => new MercureDriver(...));
     *
     * @param  Closure(array, Container): RealtimeDriver  $callback
     */
    public function extend(string $driver, Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    public function set(string $name, RealtimeDriver $driver): static
    {
        $this->connections[$name] = $driver;

        return $this;
    }

    public function fake(): RealtimeFake
    {
        return $this->fake = new RealtimeFake();
    }

    public function isFake(): bool
    {
        return $this->fake !== null;
    }

    protected function resolve(string $name): RealtimeDriver
    {
        $config = ((array) $this->app['config']->get('message-bus.realtime.connections', []))[$name] ?? null;

        if (! is_array($config)) {
            throw new InvalidArgumentException(sprintf('Message Bus realtime connection "%s" is not defined.', $name));
        }

        $driver = $config['driver'] ?? $name;

        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])($config, $this->app);
        }

        return match ($driver) {
            'broadcast' => new BroadcastDriver($this->app->make('Illuminate\Contracts\Broadcasting\Factory'), $config['connection'] ?? null),
            'webhook' => new WebhookDriver($this->app->make('Illuminate\Http\Client\Factory'), $config),
            'log' => new LogDriver($this->app->make('log')->channel($config['channel'] ?? null), $config['level'] ?? 'info'),
            'null' => new NullDriver(),
            default => throw new InvalidArgumentException(sprintf('Unsupported realtime driver "%s" for connection "%s".', $driver, $name)),
        };
    }
}
