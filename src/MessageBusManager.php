<?php

namespace Siberfx\MessageBus;

use Closure;
use Illuminate\Contracts\Container\Container;
use Siberfx\MessageBus\Bus\MessageBus;
use Siberfx\MessageBus\Contracts\MessageBusInterface;
use Siberfx\MessageBus\Contracts\MiddlewareInterface;
use Siberfx\MessageBus\Contracts\RealtimeDriver;
use Siberfx\MessageBus\Contracts\TransportInterface;
use Siberfx\MessageBus\Exception\InvalidArgumentException;
use Siberfx\MessageBus\Handler\HandlerDescriptor;
use Siberfx\MessageBus\Handler\HandlersLocator;
use Siberfx\MessageBus\Middleware\BroadcastMiddleware;
use Siberfx\MessageBus\Middleware\HandleMessageMiddleware;
use Siberfx\MessageBus\Middleware\LoggingMiddleware;
use Siberfx\MessageBus\Middleware\PrepareEnvelopeMiddleware;
use Siberfx\MessageBus\Middleware\SendMessageMiddleware;
use Siberfx\MessageBus\Realtime\BroadcastResolver;
use Siberfx\MessageBus\Realtime\RealtimeManager;
use Siberfx\MessageBus\Stamp\StampInterface;
use Siberfx\MessageBus\Support\MessageMetadata;
use Siberfx\MessageBus\Support\MessageRegistry;
use Siberfx\MessageBus\Testing\MessageBusFake;
use Siberfx\MessageBus\Testing\RealtimeFake;
use Siberfx\MessageBus\Transport\SendersLocator;
use Siberfx\MessageBus\Transport\TransportManager;

/**
 * Entry point of the package (the MessageBus facade).
 */
class MessageBusManager
{
    /**
     * @var array<string, MessageBusInterface>
     */
    protected array $buses = [];

    protected ?MessageBusFake $fake = null;

    public function __construct(protected Container $app)
    {
    }

    /**
     * Get a bus by name (defaults to "default_bus").
     */
    public function bus(?string $name = null): MessageBusInterface
    {
        $name ??= $this->defaultBus();

        if ($this->fake !== null) {
            return $this->fake->bus($name);
        }

        return $this->realBus($name);
    }

    /**
     * Get the real bus, bypassing any fake.
     */
    public function realBus(?string $name = null): MessageBusInterface
    {
        $name ??= $this->defaultBus();

        return $this->buses[$name] ??= $this->createBus($name);
    }

    /**
     * Dispatch a message. The bus is taken from #[AsMessage(bus: ...)] or the default bus.
     *
     * @param  StampInterface[]  $stamps
     */
    public function dispatch(object $message, array $stamps = [], ?string $bus = null): Envelope
    {
        return $this->bus($bus ?? $this->busFor($message))->dispatch($message, $stamps);
    }

    /**
     * Dispatch a query and return the result of its handler.
     *
     * @param  StampInterface[]  $stamps
     */
    public function ask(object $query, array $stamps = [], ?string $bus = null): mixed
    {
        $bus ??= $this->busFor($query) ?? $this->app['config']->get('message-bus.default_query_bus');

        return $this->bus($bus)->ask($query, $stamps);
    }

    /**
     * Publish directly to realtime channels, without a message.
     *
     * @param  string|list<string>  $channels
     * @param  array<string, mixed>  $payload
     */
    public function publish(string|array $channels, string $event, array $payload = [], ?string $connection = null): void
    {
        $this->realtimeManager()->publish($channels, $event, $payload, $connection);
    }

    /**
     * Register a handler at runtime.
     *
     * @param  array{method?: string, bus?: string, from_transport?: string, priority?: int, name?: string}  $options
     */
    public function handle(string $messageType, mixed $handler, array $options = []): static
    {
        $this->handlers()->add($messageType, new HandlerDescriptor($handler, $options));

        return $this;
    }

    /**
     * Route a message type to one or more transports at runtime.
     *
     * @param  string|list<string>  $transports
     */
    public function route(string $messageType, string|array $transports): static
    {
        $this->senders()->route($messageType, $transports);

        return $this;
    }

    /**
     * Allow the frontend to dispatch the message class under the given alias.
     */
    public function expose(string $alias, string $messageClass): static
    {
        $this->registry()->expose($alias, $messageClass);

        return $this;
    }

    public function transport(string $name): TransportInterface
    {
        return $this->transports()->get($name);
    }

    public function realtime(?string $connection = null): RealtimeDriver
    {
        return $this->realtimeManager()->connection($connection);
    }

    /**
     * @param  Closure(array, string, Container): TransportInterface  $callback
     */
    public function extendTransport(string $driver, Closure $callback): static
    {
        $this->transports()->extend($driver, $callback);

        return $this;
    }

    /**
     * @param  Closure(array, Container): RealtimeDriver  $callback
     */
    public function extendRealtime(string $driver, Closure $callback): static
    {
        $this->realtimeManager()->extend($driver, $callback);

        return $this;
    }

    /**
     * Record dispatched messages instead of handling them.
     *
     * @param  list<class-string>  $only  Fake only these message types.
     */
    public function fake(array $only = []): MessageBusFake
    {
        return $this->fake = new MessageBusFake($this, $only);
    }

    public function fakeRealtime(): RealtimeFake
    {
        return $this->realtimeManager()->fake();
    }

    public function isFake(): bool
    {
        return $this->fake !== null;
    }

    public function defaultBus(): string
    {
        return (string) $this->app['config']->get('message-bus.default_bus', 'command.bus');
    }

    /**
     * @return list<string>
     */
    public function busNames(): array
    {
        return array_keys((array) $this->app['config']->get('message-bus.buses', []));
    }

    public function handlers(): HandlersLocator
    {
        return $this->app->make(HandlersLocator::class);
    }

    public function senders(): SendersLocator
    {
        return $this->app->make(SendersLocator::class);
    }

    public function transports(): TransportManager
    {
        return $this->app->make(TransportManager::class);
    }

    public function realtimeManager(): RealtimeManager
    {
        return $this->app->make(RealtimeManager::class);
    }

    public function registry(): MessageRegistry
    {
        return $this->app->make(MessageRegistry::class);
    }

    /**
     * Forget the resolved buses so they are rebuilt from config.
     */
    public function forgetBuses(): static
    {
        $this->buses = [];

        return $this;
    }

    protected function busFor(object $message): ?string
    {
        return MessageMetadata::asMessage($message::class)?->bus;
    }

    protected function createBus(string $name): MessageBusInterface
    {
        // Bus names usually contain dots ("command.bus"), so no dot notation here.
        $config = ((array) $this->app['config']->get('message-bus.buses', []))[$name] ?? null;

        if (! is_array($config)) {
            throw new InvalidArgumentException(sprintf('Message bus "%s" is not defined in config/message-bus.php.', $name));
        }

        return new MessageBus(
            $name,
            $this->middlewareFor($name, $config),
            (bool) $this->app['config']->get('message-bus.unwrap_exceptions', true),
        );
    }

    /**
     * The stack: prepare → [logging] → broadcast → your middleware → send → handle.
     *
     * @return list<MiddlewareInterface>
     */
    protected function middlewareFor(string $name, array $config): array
    {
        $appConfig = $this->app['config'];
        $events = $this->app->make('events');

        $middleware = [new PrepareEnvelopeMiddleware($name)];

        if ($appConfig->get('message-bus.logging.enabled', false)) {
            $middleware[] = new LoggingMiddleware(
                $this->app->make('log')->channel($appConfig->get('message-bus.logging.channel')),
                $appConfig->get('message-bus.logging.level', 'debug'),
            );
        }

        if ($appConfig->get('message-bus.realtime.enabled', true)) {
            $middleware[] = new BroadcastMiddleware(
                $this->app->make(BroadcastResolver::class),
                $this->realtimeManager(),
                $events,
                (bool) $appConfig->get('message-bus.realtime.throw', false),
            );
        }

        foreach ((array) ($config['middleware'] ?? []) as $definition) {
            $middleware[] = $this->resolveMiddleware($definition);
        }

        $middleware[] = new SendMessageMiddleware($this->senders(), $events);
        $middleware[] = new HandleMessageMiddleware(
            $this->handlers(),
            $this->app,
            (bool) ($config['allow_no_handlers'] ?? false),
            $events,
        );

        return $middleware;
    }

    protected function resolveMiddleware(mixed $definition): MiddlewareInterface
    {
        $middleware = is_string($definition) ? $this->app->make($definition) : $definition;

        if (! $middleware instanceof MiddlewareInterface) {
            throw new InvalidArgumentException(sprintf(
                'Bus middleware must implement %s, [%s] given.',
                MiddlewareInterface::class,
                get_debug_type($middleware),
            ));
        }

        return $middleware;
    }
}
