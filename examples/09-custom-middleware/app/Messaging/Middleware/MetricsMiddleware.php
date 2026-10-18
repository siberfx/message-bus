<?php

namespace App\Messaging\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Siberfx\MessageBus\Contracts\MiddlewareInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\BusNameStamp;
use Siberfx\MessageBus\Stamp\HandledStamp;
use Siberfx\MessageBus\Stamp\ReceivedStamp;
use Siberfx\MessageBus\Stamp\SentStamp;
use Siberfx\MessageBus\Stamp\UniqueIdStamp;
use Throwable;

/**
 * Measures how long each message takes and whether it was sent or handled.
 * Swap Log for StatsD / Prometheus / Pulse recorders in your app.
 */
final class MetricsMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, Closure $next): Envelope
    {
        $started = hrtime(true);
        $type = class_basename($envelope->getMessage());

        try {
            $result = $next($envelope);
        } catch (Throwable $e) {
            Log::channel('metrics')->warning('message.failed', [
                'type' => $type,
                'id' => $envelope->last(UniqueIdStamp::class)?->id,
                'error' => $e::class,
                'ms' => $this->elapsed($started),
            ]);

            throw $e;
        }

        Log::channel('metrics')->info('message.processed', [
            'type' => $type,
            'bus' => $result->last(BusNameStamp::class)?->busName,
            'outcome' => $result->last(HandledStamp::class) ? 'handled' : ($result->last(SentStamp::class) ? 'sent' : 'ignored'),
            'from' => $result->last(ReceivedStamp::class)?->transportName ?? 'dispatch',
            'handlers' => count($result->all(HandledStamp::class)),
            'ms' => $this->elapsed($started),
        ]);

        return $result;
    }

    private function elapsed(int $started): float
    {
        return round((hrtime(true) - $started) / 1e6, 2);
    }
}
