<?php

namespace Siberfx\MessageBus\Transport\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Siberfx\MessageBus\Contracts\ListableReceiverInterface;
use Siberfx\MessageBus\Contracts\MessageCountAwareInterface;
use Siberfx\MessageBus\Contracts\SerializerInterface;
use Siberfx\MessageBus\Contracts\TransportInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Exception\MessageDecodingFailedException;
use Siberfx\MessageBus\Stamp\DelayStamp;
use Siberfx\MessageBus\Stamp\TransportMessageIdStamp;

/**
 * Stores messages in a database table and lets `message-bus:consume`
 * pull them. Also a great failure transport: failed messages can be
 * listed, inspected and retried with the failed:* commands.
 */
class DatabaseTransport implements ListableReceiverInterface, MessageCountAwareInterface, TransportInterface
{
    public function __construct(
        protected ConnectionInterface $connection,
        protected SerializerInterface $serializer,
        protected string $table = 'message_bus_messages',
        protected string $queue = 'default',
        protected int $redeliverTimeout = 3600,
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        $encoded = $this->serializer->encode($envelope);
        $now = Carbon::now();
        $delay = $envelope->last(DelayStamp::class)?->delay ?? 0;

        $id = $this->query()->insertGetId([
            'body' => $encoded['body'],
            'headers' => json_encode($encoded['headers'] ?? []),
            'queue_name' => $this->queue,
            'created_at' => $now,
            'available_at' => $now->copy()->addMilliseconds($delay),
            'delivered_at' => null,
        ]);

        return $envelope->with(new TransportMessageIdStamp($id));
    }

    public function get(): iterable
    {
        return $this->connection->transaction(function () {
            $now = Carbon::now();

            $row = $this->query()
                ->where('queue_name', $this->queue)
                ->where('available_at', '<=', $now)
                ->where(function (Builder $query) use ($now) {
                    $query->whereNull('delivered_at')
                        ->orWhere('delivered_at', '<', $now->copy()->subSeconds($this->redeliverTimeout));
                })
                ->orderBy('available_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return [];
            }

            $this->query()->where('id', $row->id)->update(['delivered_at' => $now]);

            return [$this->decodeRow($row)];
        });
    }

    public function ack(Envelope $envelope): void
    {
        $this->delete($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->delete($envelope);
    }

    public function all(?int $limit = null): iterable
    {
        $rows = $this->query()
            ->where('queue_name', $this->queue)
            ->orderBy('id')
            ->when($limit !== null, fn (Builder $query) => $query->limit($limit))
            ->get();

        foreach ($rows as $row) {
            yield $this->decodeRow($row);
        }
    }

    public function find(mixed $id): ?Envelope
    {
        $row = $this->query()->where('queue_name', $this->queue)->where('id', $id)->first();

        return $row ? $this->decodeRow($row) : null;
    }

    public function getMessageCount(): int
    {
        return $this->query()->where('queue_name', $this->queue)->count();
    }

    protected function decodeRow(object $row): Envelope
    {
        try {
            $envelope = $this->serializer->decode([
                'body' => $row->body,
                'headers' => json_decode($row->headers ?? '[]', true) ?: [],
            ]);
        } catch (MessageDecodingFailedException $e) {
            $this->query()->where('id', $row->id)->delete();

            throw $e;
        }

        return $envelope->with(new TransportMessageIdStamp($row->id));
    }

    protected function delete(Envelope $envelope): void
    {
        $id = $envelope->last(TransportMessageIdStamp::class)?->id;

        if ($id !== null) {
            $this->query()->where('id', $id)->delete();
        }
    }

    protected function query(): Builder
    {
        return $this->connection->table($this->table);
    }
}
