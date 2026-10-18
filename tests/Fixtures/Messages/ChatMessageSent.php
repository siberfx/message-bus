<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Messages;

use Siberfx\MessageBus\Attribute\AsMessage;
use Siberfx\MessageBus\Attribute\BroadcastOn;

#[AsMessage(alias: 'chat.send', exposed: true)]
#[BroadcastOn('private-chat.{roomId}', as: 'chat.message.sent', withResult: true, toOthers: true)]
final class ChatMessageSent
{
    public function __construct(
        public readonly int $roomId,
        public readonly string $body,
    ) {
    }
}
