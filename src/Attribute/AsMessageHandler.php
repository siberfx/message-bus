<?php

namespace Siberfx\MessageBus\Attribute;

use Attribute;

/**
 * Registers a class (its __invoke method) or a method as a message handler.
 * The handled message is inferred from the first parameter's type unless
 * "handles" is given.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class AsMessageHandler
{
    public function __construct(
        public readonly ?string $bus = null,
        public readonly ?string $fromTransport = null,
        public readonly ?string $handles = null,
        public readonly ?string $method = null,
        public readonly int $priority = 0,
    ) {
    }
}
