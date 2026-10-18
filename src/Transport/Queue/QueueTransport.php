<?php

namespace Siberfx\MessageBus\Transport\Queue;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Siberfx\MessageBus\Contracts\SerializerInterface;
use Siberfx\MessageBus\Contracts\TransportInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\DelayStamp;
use Siberfx\MessageBus\Stamp\TransportMessageIdStamp;

/**
 * Pushes envelopes onto any Laravel queue connection (redis, database, sqs,
 * beanstalkd, kafka, ...). Messages are consumed by `php artisan queue:work`;
 * retries and the failure transport are still handled by Message Bus.
 */
class QueueTransport implements TransportInterface
{
    public function __construct(
        protected QueueFactory $queue,
        protected SerializerInterface $serializer,
        protected string $name,
        protected ?string $connection = null,
        protected ?string $queueName = null,
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        $job = new ConsumeEnvelopeJob($this->name, $this->serializer->encode($envelope));
        $queue = $this->queue->connection($this->connection);
        $delay = $envelope->last(DelayStamp::class)?->delay ?? 0;

        $id = $delay > 0
            ? $queue->laterOn($this->queueName, (int) ceil($delay / 1000), $job)
            : $queue->pushOn($this->queueName, $job);

        return $id === null ? $envelope : $envelope->with(new TransportMessageIdStamp($id));
    }

    public function get(): iterable
    {
        return [];
    }

    public function ack(Envelope $envelope): void
    {
    }

    public function reject(Envelope $envelope): void
    {
    }
}
