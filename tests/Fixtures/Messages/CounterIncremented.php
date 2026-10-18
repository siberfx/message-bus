<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Messages;

use Siberfx\MessageBus\Contracts\ShouldBroadcastLive;

final class CounterIncremented implements ShouldBroadcastLive
{
    public function __construct(
        public readonly int $value,
        public readonly bool $silent = false,
    ) {
    }

    public function broadcastOn(): array
    {
        return ['counters'];
    }

    public function broadcastAs(): string
    {
        return 'counter.incremented';
    }

    public function broadcastWith(): array
    {
        return ['value' => $this->value];
    }

    public function broadcastWhen(): bool
    {
        return ! $this->silent;
    }
}
