<?php

namespace Siberfx\MessageBus\Testing;

use Closure;
use Siberfx\MessageBus\Contracts\MessageBusInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Exception\LogicException;
use Siberfx\MessageBus\Stamp\BusNameStamp;
use Siberfx\MessageBus\Stamp\HandledStamp;

class FakeBus implements MessageBusInterface
{
    /**
     * @param  Closure(): MessageBusInterface  $realBus
     */
    public function __construct(
        protected string $name,
        protected MessageBusFake $fake,
        protected Closure $realBus,
    ) {
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);

        if (! $this->fake->shouldFake($envelope->getMessage())) {
            return ($this->realBus)()->dispatch($envelope);
        }

        if ($envelope->last(BusNameStamp::class) === null) {
            $envelope = $envelope->with(new BusNameStamp($this->name));
        }

        return $this->fake->record($envelope);
    }

    public function ask(object $message, array $stamps = []): mixed
    {
        $envelope = Envelope::wrap($message, $stamps);

        if (! $this->fake->shouldFake($envelope->getMessage())) {
            return ($this->realBus)()->ask($envelope);
        }

        $handled = $this->dispatch($envelope)->last(HandledStamp::class);

        if ($handled === null) {
            throw new LogicException(sprintf(
                'No fake response defined for [%s]. Use MessageBus::fake()->respond(%s::class, $result).',
                $envelope->getMessage()::class,
                class_basename($envelope->getMessage()),
            ));
        }

        return $handled->result;
    }

    public function name(): string
    {
        return $this->name;
    }
}
