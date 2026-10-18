<?php

namespace Siberfx\MessageBus\Middleware;

use Closure;
use Illuminate\Contracts\Validation\Factory;
use Siberfx\MessageBus\Contracts\MiddlewareInterface;
use Siberfx\MessageBus\Contracts\ValidatesPayload;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\ReceivedStamp;
use Siberfx\MessageBus\Support\Normalizer;

/**
 * Validates messages implementing ValidatesPayload against their rules()
 * before they are sent or handled. Throws a ValidationException.
 */
class ValidationMiddleware implements MiddlewareInterface
{
    public function __construct(protected Factory $validator)
    {
    }

    public function handle(Envelope $envelope, Closure $next): Envelope
    {
        $message = $envelope->getMessage();

        if ($message instanceof ValidatesPayload && $envelope->last(ReceivedStamp::class) === null) {
            $this->validator
                ->make(Normalizer::toArray($message), $message::rules())
                ->validate();
        }

        return $next($envelope);
    }
}
