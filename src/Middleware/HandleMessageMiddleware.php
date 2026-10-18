<?php

namespace Siberfx\MessageBus\Middleware;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Siberfx\MessageBus\Contracts\HandlersLocatorInterface;
use Siberfx\MessageBus\Contracts\MiddlewareInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Events\MessageHandled;
use Siberfx\MessageBus\Exception\HandlerFailedException;
use Siberfx\MessageBus\Exception\NoHandlerForMessageException;
use Siberfx\MessageBus\Stamp\BusNameStamp;
use Siberfx\MessageBus\Stamp\HandledStamp;
use Siberfx\MessageBus\Stamp\ReceivedStamp;
use Throwable;

/**
 * Calls every handler registered for the message. Handlers that already
 * succeeded in a previous attempt (HandledStamp) are skipped on retries.
 */
class HandleMessageMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected HandlersLocatorInterface $handlers,
        protected Container $container,
        protected bool $allowNoHandlers = false,
        protected ?Dispatcher $events = null,
    ) {
    }

    public function handle(Envelope $envelope, Closure $next): Envelope
    {
        $message = $envelope->getMessage();
        $handlers = $this->handlers->getHandlers($envelope);

        if ($handlers === [] && ! $this->allowNoHandlers) {
            throw NoHandlerForMessageException::forMessage(
                $message::class,
                $envelope->last(BusNameStamp::class)?->busName ?? 'default',
            );
        }

        $alreadyHandled = array_map(
            fn (HandledStamp $stamp) => $stamp->handlerName,
            $envelope->all(HandledStamp::class),
        );

        $exceptions = [];

        foreach ($handlers as $descriptor) {
            $name = $descriptor->name();

            if (in_array($name, $alreadyHandled, true)) {
                continue;
            }

            try {
                $result = ($descriptor->resolve($this->container))($message, $envelope);

                $envelope = $envelope->with(new HandledStamp($result, $name));
            } catch (Throwable $e) {
                $exceptions[$name] = $e;
            }
        }

        if ($exceptions !== []) {
            throw new HandlerFailedException($envelope, $exceptions);
        }

        $this->events?->dispatch(new MessageHandled($envelope, $envelope->last(ReceivedStamp::class)?->transportName));

        return $next($envelope);
    }
}
