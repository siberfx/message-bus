<?php

namespace Siberfx\MessageBus\Transport;

use Closure;
use Illuminate\Contracts\Container\Container;
use Siberfx\MessageBus\Contracts\RetryStrategyInterface;
use Siberfx\MessageBus\Contracts\SerializerInterface;
use Siberfx\MessageBus\Contracts\TransportInterface;
use Siberfx\MessageBus\Exception\TransportException;
use Siberfx\MessageBus\Retry\MultiplierRetryStrategy;
use Siberfx\MessageBus\Transport\Database\DatabaseTransport;
use Siberfx\MessageBus\Transport\InMemory\InMemoryTransport;
use Siberfx\MessageBus\Transport\Queue\QueueTransport;
use Siberfx\MessageBus\Transport\Serialization\JsonSerializer;
use Siberfx\MessageBus\Transport\Serialization\PhpSerializer;
use Siberfx\MessageBus\Transport\Sync\SyncTransport;

/**
 * Builds and caches the transports defined in config("message-bus.transports").
 */
class TransportManager
{
    /**
     * @var array<string, TransportInterface>
     */
    protected array $transports = [];

    /**
     * @var array<string, Closure(array, string, Container): TransportInterface>
     */
    protected array $customCreators = [];

    public function __construct(protected Container $app)
    {
    }

    public function get(string $name): TransportInterface
    {
        return $this->transports[$name] ??= $this->resolve($name);
    }

    public function has(string $name): bool
    {
        return isset($this->transports[$name]) || is_array($this->config($name));
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_unique(array_merge(
            array_keys((array) $this->app['config']->get('message-bus.transports', [])),
            array_keys($this->transports),
        )));
    }

    /**
     * Use the given transport instance for the given name.
     */
    public function set(string $name, TransportInterface $transport): static
    {
        $this->transports[$name] = $transport;

        return $this;
    }

    /**
     * Register a custom transport driver:
     *
     *     MessageBus::extendTransport('kafka', fn (array $config, string $name, $app) => new KafkaTransport(...));
     *
     * @param  Closure(array, string, Container): TransportInterface  $callback
     */
    public function extend(string $driver, Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    public function forget(?string $name = null): static
    {
        if ($name === null) {
            $this->transports = [];
        } else {
            unset($this->transports[$name]);
        }

        return $this;
    }

    public function serializerFor(string $name): SerializerInterface
    {
        return $this->makeSerializer($this->config($name)['serializer'] ?? null);
    }

    public function makeSerializer(?string $serializer = null): SerializerInterface
    {
        $serializer ??= $this->app['config']->get('message-bus.serializer', 'php');

        return match ($serializer) {
            'php' => new PhpSerializer(),
            'json' => $this->app->make(JsonSerializer::class),
            default => $this->app->make($serializer),
        };
    }

    public function retryStrategy(string $name): RetryStrategyInterface
    {
        $config = array_replace(
            (array) $this->app['config']->get('message-bus.retry', []),
            (array) ($this->config($name)['retry'] ?? []),
        );

        if (isset($config['strategy'])) {
            return $this->app->make($config['strategy'], ['config' => $config]);
        }

        return new MultiplierRetryStrategy(
            maxRetries: (int) ($config['max_retries'] ?? 3),
            delay: (int) ($config['delay'] ?? 1000),
            multiplier: (float) ($config['multiplier'] ?? 2),
            maxDelay: (int) ($config['max_delay'] ?? 0),
        );
    }

    /**
     * The failure transport for the given transport. A transport may disable
     * it ("failure_transport" => null) or use its own.
     */
    public function failureTransportFor(string $name): ?string
    {
        $config = $this->config($name) ?? [];

        if (array_key_exists('failure_transport', $config)) {
            return $config['failure_transport'];
        }

        return $this->app['config']->get('message-bus.failure_transport');
    }

    protected function resolve(string $name): TransportInterface
    {
        $config = $this->config($name);

        if (! is_array($config)) {
            throw TransportException::unknown($name);
        }

        $driver = $config['driver'] ?? 'sync';

        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])($config, $name, $this->app);
        }

        return match ($driver) {
            'sync' => new SyncTransport(),
            'in-memory', 'memory' => new InMemoryTransport(
                ($config['serialize'] ?? false) ? $this->serializerFor($name) : null,
            ),
            'database' => new DatabaseTransport(
                $this->app['db']->connection($config['connection'] ?? null),
                $this->serializerFor($name),
                $config['table'] ?? 'message_bus_messages',
                $config['queue'] ?? 'default',
                (int) ($config['redeliver_timeout'] ?? 3600),
            ),
            'queue' => new QueueTransport(
                $this->app['queue'],
                $this->serializerFor($name),
                $name,
                $config['connection'] ?? null,
                $config['queue'] ?? null,
            ),
            default => throw new TransportException(sprintf(
                'Unsupported driver "%s" for Message Bus transport "%s".',
                $driver,
                $name,
            )),
        };
    }

    protected function config(string $name): ?array
    {
        $config = ((array) $this->app['config']->get('message-bus.transports', []))[$name] ?? null;

        return is_array($config) ? $config : null;
    }
}
