<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Messages;

use Siberfx\MessageBus\Attribute\AsMessage;

#[AsMessage(transport: 'async', alias: 'audit.log')]
final class AuditLog
{
    public function __construct(public readonly string $action)
    {
    }
}
