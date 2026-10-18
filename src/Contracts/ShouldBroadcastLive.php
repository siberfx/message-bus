<?php

namespace Siberfx\MessageBus\Contracts;

/**
 * Implement on a message to broadcast it after it has been handled.
 *
 * Optional methods that are picked up when present:
 *  - broadcastAs(): string                 the event name
 *  - broadcastWith(): array                the "data" part of the payload
 *  - broadcastVia(): ?string               the realtime connection name
 *  - broadcastWhen(): bool                 return false to skip broadcasting
 *  - broadcastToOthers(): bool             exclude the dispatching socket
 *  - broadcastWithResult(): bool           include the handler result
 */
interface ShouldBroadcastLive
{
    /**
     * @return string|list<string>
     */
    public function broadcastOn(): string|array;
}
