<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Messages;

use Siberfx\MessageBus\Attribute\AsMessage;

/**
 * Has an alias but is NOT exposed to the frontend.
 */
#[AsMessage(alias: 'internal')]
final class InternalMessage
{
}
