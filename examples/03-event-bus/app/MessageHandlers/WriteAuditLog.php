<?php

namespace App\MessageHandlers;

use App\Contracts\Auditable;
use App\Models\AuditEntry;
use Siberfx\MessageBus\Attribute\AsMessageHandler;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\BusNameStamp;
use Siberfx\MessageBus\Stamp\HttpOriginStamp;
use Siberfx\MessageBus\Stamp\UniqueIdStamp;

/**
 * Type-hinting an interface handles every message implementing it.
 * Handlers receive the Envelope as second argument when they ask for it.
 */
#[AsMessageHandler(priority: -50)]
final class WriteAuditLog
{
    public function __invoke(Auditable $message, Envelope $envelope): void
    {
        AuditEntry::create([
            'message_id' => $envelope->last(UniqueIdStamp::class)?->id,
            'type' => $message::class,
            'bus' => $envelope->last(BusNameStamp::class)?->busName,
            'user_id' => $envelope->last(HttpOriginStamp::class)?->userId,
            'description' => $message->auditDescription(),
        ]);
    }
}
