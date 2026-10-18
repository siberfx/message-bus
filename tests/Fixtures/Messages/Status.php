<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Messages;

enum Status: string
{
    case Active = 'active';
    case Banned = 'banned';
}
