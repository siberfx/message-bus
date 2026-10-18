<?php

namespace Siberfx\MessageBus\Attribute;

use Attribute;

/**
 * Declares message level configuration:
 *  - transport: where the message is routed when no config routing matches
 *  - alias:     a short public name ("chat.send") used by HTTP & broadcasting
 *  - exposed:   allow the frontend to dispatch this message over HTTP
 *  - bus:       the bus used by MessageBus::dispatch() for this message
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsMessage
{
    /**
     * @param  string|list<string>|null  $transport
     */
    public function __construct(
        public readonly string|array|null $transport = null,
        public readonly ?string $alias = null,
        public readonly bool $exposed = false,
        public readonly ?string $bus = null,
    ) {
    }
}
