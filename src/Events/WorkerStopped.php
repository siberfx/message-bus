<?php

namespace Siberfx\MessageBus\Events;

class WorkerStopped
{
    /**
     * @param  list<string>  $transportNames
     */
    public function __construct(
        public readonly array $transportNames,
        public readonly int $handledCount,
        public readonly string $reason,
    ) {
    }
}
