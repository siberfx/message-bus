<?php

namespace Siberfx\MessageBus\Stamp;

/**
 * Prevents the message from being broadcast, even if it is broadcastable.
 */
final class NoBroadcastStamp implements StampInterface
{
}
