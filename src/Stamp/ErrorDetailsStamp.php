<?php

namespace Siberfx\MessageBus\Stamp;

use Siberfx\MessageBus\Exception\HandlerFailedException;
use Throwable;

/**
 * Describes the error that made the message fail.
 */
final class ErrorDetailsStamp implements StampInterface
{
    public function __construct(
        public readonly string $exceptionClass,
        public readonly string $exceptionMessage,
        public readonly int|string $exceptionCode = 0,
        public readonly ?string $trace = null,
    ) {
    }

    public static function create(Throwable $throwable): self
    {
        if ($throwable instanceof HandlerFailedException && $throwable->getPrevious() !== null) {
            $throwable = $throwable->getPrevious();
        }

        return new self(
            $throwable::class,
            $throwable->getMessage(),
            $throwable->getCode(),
            $throwable->getTraceAsString(),
        );
    }
}
