<?php

namespace Siberfx\MessageBus\Transport\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Siberfx\MessageBus\Contracts\SerializerInterface;
use Siberfx\MessageBus\Transport\TransportManager;
use Siberfx\MessageBus\Worker\EnvelopeProcessor;

/**
 * The Laravel job carrying an encoded envelope through the queue.
 */
class ConsumeEnvelopeJob implements ShouldQueue
{
    use Queueable;

    /**
     * Retries are handled by Message Bus's retry strategy.
     */
    public int $tries = 1;

    /**
     * @param  array{body: string, headers: array<string, mixed>}  $encodedEnvelope
     */
    public function __construct(
        public string $transportName,
        public array $encodedEnvelope,
    ) {
    }

    public function handle(TransportManager $transports, EnvelopeProcessor $processor): void
    {
        $transport = $transports->get($this->transportName);
        $serializer = $transports->serializerFor($this->transportName);

        $processor->process($this->transportName, $serializer->decode($this->encodedEnvelope), $transport);
    }

    public function displayName(): string
    {
        return 'MessageBus: '.($this->encodedEnvelope['headers']['type'] ?? 'message');
    }
}
