<?php

namespace Siberfx\MessageBus\Transport\Sync;

use Siberfx\MessageBus\Contracts\TransportInterface;
use Siberfx\MessageBus\Envelope;

/**
 * Routing a message to a "sync" transport means: handle it right now, in
 * the current process. The SendMessageMiddleware short-circuits on it.
 */
class SyncTransport implements TransportInterface
{
    public function send(Envelope $envelope): Envelope
    {
        return $envelope;
    }

    public function get(): iterable
    {
        return [];
    }

    public function ack(Envelope $envelope): void
    {
    }

    public function reject(Envelope $envelope): void
    {
    }
}
