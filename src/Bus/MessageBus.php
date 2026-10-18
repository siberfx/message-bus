<?php

namespace Siberfx\MessageBus\Bus;

use Closure;
use Siberfx\MessageBus\Contracts\MessageBusInterface;
use Siberfx\MessageBus\Contracts\MiddlewareInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Exception\HandlerFailedException;
use Siberfx\MessageBus\Exception\LogicException;
use Siberfx\MessageBus\Stamp\HandledStamp;
use Siberfx\MessageBus\Stamp\ReceivedStamp;

class MessageBus implements MessageBusInterface
{
    /**
     * @param  list<MiddlewareInterface>  $middleware
     */
    public function __construct(
        protected string $name,
        protected array $middleware = [],
        protected bool $unwrapExceptions = true,
    ) {
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);

        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn (Closure $next, MiddlewareInterface $middleware) => fn (Envelope $envelope) => $middleware->handle($envelope, $next),
            fn (Envelope $envelope) => $envelope,
        );

        try {
            return $pipeline($envelope);
        } catch (HandlerFailedException $e) {
            // When dispatching synchronously from application code, surface the
            // original exception (e.g. a ValidationException) instead of the wrapper.
            if ($this->unwrapExceptions
                && $envelope->last(ReceivedStamp::class) === null
                && count($wrapped = $e->getWrappedExceptions()) === 1) {
                throw reset($wrapped);
            }

            throw $e;
        }
    }

    public function ask(object $message, array $stamps = []): mixed
    {
        $envelope = $this->dispatch($message, $stamps);
        $handled = $envelope->all(HandledStamp::class);

        if ($handled === []) {
            throw new LogicException(sprintf(
                'Message of type "%s" was handled zero times. Exactly one handler is expected when asking a question; is it routed to an async transport?',
                $envelope->getMessage()::class,
            ));
        }

        if (count($handled) > 1) {
            throw new LogicException(sprintf(
                'Message of type "%s" was handled %d times (%s). Exactly one handler is expected when asking a question.',
                $envelope->getMessage()::class,
                count($handled),
                implode(', ', array_map(fn (HandledStamp $stamp) => $stamp->handlerName, $handled)),
            ));
        }

        return $handled[0]->result;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<MiddlewareInterface>
     */
    public function middleware(): array
    {
        return $this->middleware;
    }
}
