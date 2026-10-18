<?php

namespace Siberfx\MessageBus\Handler;

use Closure;
use Illuminate\Contracts\Container\Container;
use Siberfx\MessageBus\Exception\InvalidArgumentException;

/**
 * Describes a handler and the options under which it runs.
 *
 * Supported handler formats:
 *  - Handler::class                    (invokable, resolved from the container)
 *  - 'Handler@method' / [Handler::class, 'method']
 *  - [$object, 'method'], a Closure or any callable object
 */
final class HandlerDescriptor
{
    private ?string $name = null;

    /**
     * @param  array{method?: string, bus?: string|null, from_transport?: string|null, priority?: int, name?: string}  $options
     */
    public function __construct(
        private readonly mixed $handler,
        private readonly array $options = [],
    ) {
    }

    public function name(): string
    {
        if ($this->name !== null) {
            return $this->name;
        }

        if (isset($this->options['name'])) {
            return $this->name = $this->options['name'];
        }

        [$target, $method] = $this->target();

        $this->name = match (true) {
            $target instanceof Closure => 'Closure#'.spl_object_id($target),
            is_object($target) => $target::class.'::'.$method,
            default => $target.'::'.$method,
        };

        return $this->name;
    }

    public function bus(): ?string
    {
        return $this->options['bus'] ?? null;
    }

    public function fromTransport(): ?string
    {
        return $this->options['from_transport'] ?? null;
    }

    public function priority(): int
    {
        return (int) ($this->options['priority'] ?? 0);
    }

    public function handler(): mixed
    {
        return $this->handler;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function resolve(Container $container): callable
    {
        [$target, $method] = $this->target();

        if ($target instanceof Closure) {
            return $target;
        }

        $instance = is_string($target) ? $container->make($target) : $target;

        if (! method_exists($instance, $method) && ! ($method === '__invoke' && is_callable($instance))) {
            throw new InvalidArgumentException(sprintf('Handler "%s" has no "%s" method.', $instance::class, $method));
        }

        return [$instance, $method];
    }

    /**
     * @return array{0: Closure|object|string, 1: string}
     */
    private function target(): array
    {
        $handler = $this->handler;
        $method = $this->options['method'] ?? '__invoke';

        if ($handler instanceof Closure) {
            return [$handler, '__invoke'];
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            return explode('@', $handler, 2);
        }

        if (is_array($handler) && count($handler) === 2) {
            return [$handler[0], $handler[1]];
        }

        if (is_string($handler) || is_object($handler)) {
            return [$handler, $method];
        }

        throw new InvalidArgumentException('Invalid message handler definition.');
    }
}
