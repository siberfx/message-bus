<?php

namespace Siberfx\MessageBus\Contracts;

use Closure;
use Siberfx\MessageBus\Envelope;

interface MiddlewareInterface
{
    /**
     * @param  Closure(Envelope): Envelope  $next
     */
    public function handle(Envelope $envelope, Closure $next): Envelope;
}
