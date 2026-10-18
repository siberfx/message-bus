<?php

namespace Siberfx\MessageBus\Contracts;

interface MessageCountAwareInterface
{
    public function getMessageCount(): int;
}
