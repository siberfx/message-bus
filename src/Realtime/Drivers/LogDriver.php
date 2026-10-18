<?php

namespace Siberfx\MessageBus\Realtime\Drivers;

use Psr\Log\LoggerInterface;
use Siberfx\MessageBus\Contracts\RealtimeDriver;

/**
 * Writes realtime events to the log. Handy in local development.
 */
class LogDriver implements RealtimeDriver
{
    public function __construct(
        protected LoggerInterface $logger,
        protected string $level = 'info',
    ) {
    }

    public function publish(array $channels, string $event, array $payload = []): void
    {
        $this->logger->log($this->level, sprintf('[message-bus] Broadcasting "%s" on [%s]', $event, implode(', ', $channels)), $payload);
    }
}
