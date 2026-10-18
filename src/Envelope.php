<?php

namespace Siberfx\MessageBus;

use InvalidArgumentException;
use Siberfx\MessageBus\Stamp\StampInterface;

/**
 * Wraps a message together with the stamps (metadata) collected while it
 * travels through buses, middleware and transports.
 */
final class Envelope
{
    /**
     * @var array<class-string<StampInterface>, list<StampInterface>>
     */
    private array $stamps = [];

    /**
     * @param  StampInterface[]  $stamps
     */
    public function __construct(private readonly object $message, array $stamps = [])
    {
        if ($message instanceof self) {
            throw new InvalidArgumentException('An Envelope cannot wrap another Envelope.');
        }

        foreach ($stamps as $stamp) {
            $this->stamps[$stamp::class][] = $stamp;
        }
    }

    /**
     * Wrap the given message (or re-use the given envelope) and add stamps.
     *
     * @param  StampInterface[]  $stamps
     */
    public static function wrap(object $message, array $stamps = []): self
    {
        $envelope = $message instanceof self ? $message : new self($message);

        return $envelope->with(...$stamps);
    }

    /**
     * Return a new envelope with the given stamps added.
     */
    public function with(StampInterface ...$stamps): self
    {
        $cloned = clone $this;

        foreach ($stamps as $stamp) {
            $cloned->stamps[$stamp::class][] = $stamp;
        }

        return $cloned;
    }

    /**
     * Return a new envelope without any stamp of the exact given class.
     *
     * @param  class-string<StampInterface>  $stampFqcn
     */
    public function withoutAll(string $stampFqcn): self
    {
        $cloned = clone $this;

        unset($cloned->stamps[$stampFqcn]);

        return $cloned;
    }

    /**
     * Return a new envelope without stamps that are instances of the given type.
     */
    public function withoutStampsOfType(string $type): self
    {
        $cloned = clone $this;

        foreach (array_keys($cloned->stamps) as $class) {
            if (is_a($class, $type, true)) {
                unset($cloned->stamps[$class]);
            }
        }

        return $cloned;
    }

    /**
     * Get the last stamp of the given class.
     *
     * @template T of StampInterface
     *
     * @param  class-string<T>  $stampFqcn
     * @return T|null
     */
    public function last(string $stampFqcn): ?StampInterface
    {
        if (empty($this->stamps[$stampFqcn])) {
            return null;
        }

        return $this->stamps[$stampFqcn][array_key_last($this->stamps[$stampFqcn])];
    }

    /**
     * Get all stamps grouped by class, or all stamps of the given class.
     *
     * @param  class-string<StampInterface>|null  $stampFqcn
     */
    public function all(?string $stampFqcn = null): array
    {
        if ($stampFqcn !== null) {
            return $this->stamps[$stampFqcn] ?? [];
        }

        return $this->stamps;
    }

    public function getMessage(): object
    {
        return $this->message;
    }
}
