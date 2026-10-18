<?php

namespace Siberfx\MessageBus\Middleware;

use Closure;
use Psr\Log\LoggerInterface;
use Siberfx\MessageBus\Contracts\MiddlewareInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\BusNameStamp;
use Siberfx\MessageBus\Stamp\HandledStamp;
use Siberfx\MessageBus\Stamp\ReceivedStamp;
use Siberfx\MessageBus\Stamp\SentStamp;
use Siberfx\MessageBus\Stamp\UniqueIdStamp;
use Throwable;

/**
 * Logs every message going through the bus.
 */
class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected LoggerInterface $logger,
        protected string $level = 'debug',
    ) {
    }

    public function handle(Envelope $envelope, Closure $next): Envelope
    {
        $context = [
            'message' => $envelope->getMessage()::class,
            'id' => $envelope->last(UniqueIdStamp::class)?->id,
            'bus' => $envelope->last(BusNameStamp::class)?->busName,
            'received_from' => $envelope->last(ReceivedStamp::class)?->transportName,
        ];

        $this->logger->log($this->level, '[message-bus] Dispatching {message}', $context);

        try {
            $result = $next($envelope);
        } catch (Throwable $e) {
            $this->logger->error('[message-bus] {message} failed: '.$e->getMessage(), $context + ['exception' => $e]);

            throw $e;
        }

        $this->logger->log($this->level, '[message-bus] Finished {message}', $context + [
            'handled_by' => array_map(fn (HandledStamp $s) => $s->handlerName, $result->all(HandledStamp::class)),
            'sent_to' => array_map(fn (SentStamp $s) => $s->senderAlias, $result->all(SentStamp::class)),
        ]);

        return $result;
    }
}
