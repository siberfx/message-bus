<?php

namespace Siberfx\MessageBus\Livewire;

/**
 * Converts message bus channel names into Livewire's Echo listener syntax:
 *
 *     LivewireEcho::listener('private-orders.1', 'order.shipped')  // "echo-private:orders.1,.order.shipped"
 *     LivewireEcho::listener('presence-room.1', 'joined')          // "echo-presence:room.1,.joined"
 *     LivewireEcho::listener('news', 'article.published')          // "echo:news,.article.published"
 */
final class LivewireEcho
{
    public static function listener(string $channel, string $event): string
    {
        $event = str_starts_with($event, '.') ? $event : '.'.$event;

        return match (true) {
            str_starts_with($channel, 'private-') => 'echo-private:'.substr($channel, 8).','.$event,
            str_starts_with($channel, 'presence-') => 'echo-presence:'.substr($channel, 9).','.$event,
            default => 'echo:'.$channel.','.$event,
        };
    }
}
