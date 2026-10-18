<?php

namespace Siberfx\MessageBus\Stamp;

/**
 * Instructs the bus to broadcast the message once it is handled, overriding
 * any broadcasting configuration declared on the message itself.
 */
final class BroadcastStamp implements StampInterface
{
    /**
     * @var list<string>
     */
    public readonly array $channels;

    /**
     * @param  string|list<string>  $channels
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        string|array $channels,
        public readonly ?string $event = null,
        public readonly ?string $via = null,
        public readonly ?array $payload = null,
        public readonly bool $withResult = false,
        public readonly bool $toOthers = false,
    ) {
        $this->channels = array_values((array) $channels);
    }
}
