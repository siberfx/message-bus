<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Messages;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Siberfx\MessageBus\Attribute\AsMessage;
use Siberfx\MessageBus\Contracts\AuthorizesDispatch;

#[AsMessage(alias: 'secret', exposed: true)]
final class SecretMessage implements AuthorizesDispatch
{
    public function __construct(public readonly string $value = '')
    {
    }

    public function authorize(?Authenticatable $user, Request $request): bool
    {
        return $user !== null;
    }
}
