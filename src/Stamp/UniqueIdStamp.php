<?php

namespace Siberfx\MessageBus\Stamp;

use Illuminate\Support\Str;

/**
 * A stable identifier for the message. It survives transports and is used
 * in broadcast payloads so frontends can correlate optimistic updates.
 */
final class UniqueIdStamp implements StampInterface
{
    public readonly string $id;

    public function __construct(?string $id = null)
    {
        $this->id = $id ?? strtolower((string) Str::ulid());
    }
}
