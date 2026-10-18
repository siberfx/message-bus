<?php

namespace Siberfx\MessageBus\Attribute;

use Attribute;

/**
 * Broadcasts the message to realtime channels once it has been handled.
 *
 * Channel names may contain {placeholders} that are resolved from the
 * message's public properties (dot notation is supported):
 *
 *     #[BroadcastOn('private-chat.{roomId}', as: 'chat.message.sent')]
 *
 * Prefix channels with "private-" or "presence-" for authorized channels.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class BroadcastOn
{
    /**
     * @var list<string>
     */
    public readonly array $channels;

    /**
     * @param  string|list<string>  $channels
     */
    public function __construct(
        string|array $channels,
        public readonly ?string $as = null,
        public readonly ?string $via = null,
        public readonly bool $withResult = false,
        public readonly bool $toOthers = false,
    ) {
        $this->channels = array_values((array) $channels);
    }
}
