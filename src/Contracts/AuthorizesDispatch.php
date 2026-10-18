<?php

namespace Siberfx\MessageBus\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Implement on an exposed message to authorize HTTP dispatches.
 * Called after the message has been hydrated from the payload.
 */
interface AuthorizesDispatch
{
    public function authorize(?Authenticatable $user, Request $request): bool;
}
