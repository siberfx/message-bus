<?php

namespace Siberfx\MessageBus\Events;

class WorkerStarted
{
    /**
     * @param  list<string>  $transportNames
     */
    public function __construct(public readonly array $transportNames)
    {
    }
}
