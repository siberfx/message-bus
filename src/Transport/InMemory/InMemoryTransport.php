<?php

namespace Siberfx\MessageBus\Transport\InMemory;

use Siberfx\MessageBus\Contracts\ListableReceiverInterface;
use Siberfx\MessageBus\Contracts\MessageCountAwareInterface;
use Siberfx\MessageBus\Contracts\SerializerInterface;
use Siberfx\MessageBus\Contracts\TransportInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\TransportMessageIdStamp;

/**
 * Keeps messages in memory. Perfect for tests: assert what was sent, then
 * consume it with the worker. Optionally round-trips messages through a
 * serializer to catch serialization issues early.
 */
class InMemoryTransport implements ListableReceiverInterface, MessageCountAwareInterface, TransportInterface
{
    /** @var list<Envelope> */
    protected array $sent = [];

    /** @var array<int, Envelope> */
    protected array $queue = [];

    /** @var list<Envelope> */
    protected array $acknowledged = [];

    /** @var list<Envelope> */
    protected array $rejected = [];

    protected int $nextId = 1;

    public function __construct(protected ?SerializerInterface $serializer = null)
    {
    }

    public function send(Envelope $envelope): Envelope
    {
        $stored = $this->serializer
            ? $this->serializer->decode($this->serializer->encode($envelope))
            : $envelope;

        $id = $this->nextId++;
        $stored = $stored->with(new TransportMessageIdStamp($id));

        $this->sent[] = $stored;
        $this->queue[$id] = $stored;

        return $envelope->with(new TransportMessageIdStamp($id));
    }

    public function get(): iterable
    {
        return array_values($this->queue);
    }

    public function ack(Envelope $envelope): void
    {
        $this->acknowledged[] = $envelope;
        $this->forget($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->rejected[] = $envelope;
        $this->forget($envelope);
    }

    public function all(?int $limit = null): iterable
    {
        return array_slice(array_values($this->queue), 0, $limit);
    }

    public function find(mixed $id): ?Envelope
    {
        return $this->queue[(int) $id] ?? null;
    }

    public function getMessageCount(): int
    {
        return count($this->queue);
    }

    /**
     * @return list<Envelope>
     */
    public function getSent(): array
    {
        return $this->sent;
    }

    /**
     * @return list<object>
     */
    public function messages(): array
    {
        return array_map(fn (Envelope $envelope) => $envelope->getMessage(), $this->sent);
    }

    /**
     * @return list<Envelope>
     */
    public function getAcknowledged(): array
    {
        return $this->acknowledged;
    }

    /**
     * @return list<Envelope>
     */
    public function getRejected(): array
    {
        return $this->rejected;
    }

    public function reset(): void
    {
        $this->sent = $this->queue = $this->acknowledged = $this->rejected = [];
    }

    protected function forget(Envelope $envelope): void
    {
        $id = $envelope->last(TransportMessageIdStamp::class)?->id;

        if ($id !== null) {
            unset($this->queue[$id]);
        }
    }
}
