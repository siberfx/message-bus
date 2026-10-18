<?php

namespace Siberfx\MessageBus\Handler;

use Siberfx\MessageBus\Contracts\HandlersLocatorInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\BusNameStamp;
use Siberfx\MessageBus\Stamp\ReceivedStamp;

/**
 * Maps message types (classes, parents, interfaces or "*") to handlers.
 */
class HandlersLocator implements HandlersLocatorInterface
{
    /**
     * @var array<string, list<HandlerDescriptor>>
     */
    protected array $handlers = [];

    public function add(string $messageType, HandlerDescriptor $descriptor): static
    {
        $this->handlers[$messageType][] = $descriptor;

        return $this;
    }

    /**
     * Register handlers using the config format:
     *
     *     Message::class => Handler::class
     *     Message::class => [HandlerA::class, [HandlerB::class, 'method'], ['handler' => C::class, 'bus' => 'event.bus']]
     *
     * @param  array<string, mixed>  $config
     */
    public function addFromConfig(array $config): static
    {
        foreach ($config as $messageType => $definitions) {
            foreach ($this->normalizeDefinitions($definitions) as [$handler, $options]) {
                $this->add($messageType, new HandlerDescriptor($handler, $options));
            }
        }

        return $this;
    }

    public function getHandlers(Envelope $envelope): array
    {
        $seen = [];
        $matched = [];

        foreach (self::listTypes($envelope) as $type) {
            foreach ($this->handlers[$type] ?? [] as $descriptor) {
                $name = $descriptor->name();

                if (isset($seen[$name]) || ! $this->shouldHandle($envelope, $descriptor)) {
                    continue;
                }

                $seen[$name] = true;
                $matched[] = $descriptor;
            }
        }

        usort($matched, fn (HandlerDescriptor $a, HandlerDescriptor $b) => $b->priority() <=> $a->priority());

        return $matched;
    }

    /**
     * @return array<string, list<HandlerDescriptor>>
     */
    public function all(): array
    {
        return $this->handlers;
    }

    /**
     * The types a message is matched against, most specific first.
     *
     * @return array<string, string>
     */
    public static function listTypes(object $message): array
    {
        $class = ($message instanceof Envelope ? $message->getMessage() : $message)::class;

        return [$class => $class]
            + class_parents($class)
            + class_implements($class)
            + ['*' => '*'];
    }

    protected function shouldHandle(Envelope $envelope, HandlerDescriptor $descriptor): bool
    {
        $bus = $descriptor->bus();

        if ($bus !== null && ($stamp = $envelope->last(BusNameStamp::class)) !== null && $stamp->busName !== $bus) {
            return false;
        }

        if (($transport = $descriptor->fromTransport()) !== null) {
            return $envelope->last(ReceivedStamp::class)?->transportName === $transport;
        }

        return true;
    }

    /**
     * @return list<array{0: mixed, 1: array<string, mixed>}>
     */
    protected function normalizeDefinitions(mixed $definitions): array
    {
        if (! is_array($definitions) || $this->isCallablePair($definitions)) {
            return [[$definitions, []]];
        }

        if (isset($definitions['handler'])) {
            $handler = $definitions['handler'];
            unset($definitions['handler']);

            return [[$handler, $definitions]];
        }

        $normalized = [];

        foreach ($definitions as $definition) {
            array_push($normalized, ...$this->normalizeDefinitions($definition));
        }

        return $normalized;
    }

    protected function isCallablePair(array $definition): bool
    {
        return array_is_list($definition)
            && count($definition) === 2
            && (is_string($definition[0]) || is_object($definition[0]))
            && is_string($definition[1])
            && method_exists($definition[0], $definition[1]);
    }
}
