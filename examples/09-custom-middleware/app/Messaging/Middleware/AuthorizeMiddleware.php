<?php

namespace App\Messaging\Middleware;

use App\Messaging\RequiresPermission;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Siberfx\MessageBus\Contracts\MiddlewareInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\ReceivedStamp;

/**
 * Checks a Gate ability for messages implementing RequiresPermission:
 *
 *     final class DeleteProject implements RequiresPermission
 *     {
 *         public function permission(): array { return ['delete', Project::find($this->projectId)]; }
 *     }
 */
final class AuthorizeMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Gate $gate)
    {
    }

    public function handle(Envelope $envelope, Closure $next): Envelope
    {
        $message = $envelope->getMessage();

        // Authorize once, in the request that dispatched the message (not in the worker).
        if ($message instanceof RequiresPermission && $envelope->last(ReceivedStamp::class) === null) {
            [$ability, $arguments] = array_pad($message->permission(), 2, []);

            if ($this->gate->denies($ability, $arguments)) {
                throw new AuthorizationException(sprintf('You may not dispatch %s.', class_basename($message)));
            }
        }

        return $next($envelope);
    }
}
