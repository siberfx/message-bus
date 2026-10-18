<?php

namespace Siberfx\MessageBus\Exception;

use Siberfx\MessageBus\Envelope;
use Throwable;

/**
 * Thrown when one or more handlers failed. The envelope carries a
 * HandledStamp for every handler that did succeed, so retries only
 * re-run the handlers that failed.
 */
class HandlerFailedException extends RuntimeException
{
    /**
     * @param  array<string, Throwable>  $exceptions  Keyed by handler name.
     */
    public function __construct(private readonly Envelope $envelope, private readonly array $exceptions)
    {
        $first = reset($exceptions) ?: null;

        parent::__construct(
            sprintf(
                'Handling "%s" failed: %s',
                $envelope->getMessage()::class,
                count($exceptions) === 1
                    ? $first?->getMessage()
                    : implode('; ', array_map(
                        fn (string $handler, Throwable $e) => $handler.': '.$e->getMessage(),
                        array_keys($exceptions),
                        $exceptions,
                    )),
            ),
            (int) ($first?->getCode() ?? 0),
            $first,
        );
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    /**
     * @return array<string, Throwable>
     */
    public function getWrappedExceptions(?string $class = null, bool $recursive = false): array
    {
        $exceptions = $this->exceptions;

        if ($recursive) {
            foreach ($exceptions as $key => $exception) {
                if ($exception instanceof self) {
                    unset($exceptions[$key]);
                    $exceptions += $exception->getWrappedExceptions(recursive: true);
                }
            }
        }

        if ($class === null) {
            return $exceptions;
        }

        return array_filter($exceptions, fn (Throwable $e) => $e instanceof $class);
    }
}
