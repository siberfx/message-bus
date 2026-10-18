<?php

namespace Siberfx\MessageBus\Transport\Serialization;

use JsonException;
use Siberfx\MessageBus\Contracts\SerializerInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Exception\MessageDecodingFailedException;
use Siberfx\MessageBus\Stamp\NonSendableStampInterface;
use Siberfx\MessageBus\Support\MessageHydrator;
use Siberfx\MessageBus\Support\MessageRegistry;
use Siberfx\MessageBus\Support\Normalizer;
use Throwable;

/**
 * Stores the message body as JSON so other languages / tools can read it.
 * Messages are rebuilt through their constructor by the MessageHydrator.
 */
class JsonSerializer implements SerializerInterface
{
    public function __construct(
        protected MessageHydrator $hydrator,
        protected ?MessageRegistry $registry = null,
    ) {
    }

    public function encode(Envelope $envelope): array
    {
        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);
        $message = $envelope->getMessage();

        $stamps = [];
        foreach ($envelope->all() as $list) {
            array_push($stamps, ...$list);
        }

        return [
            'body' => json_encode(Normalizer::toArray($message), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'headers' => array_filter([
                'type' => $message::class,
                'alias' => $this->registry?->aliasFor($message::class),
                'format' => 'json',
                'stamps' => $stamps !== [] ? base64_encode(serialize($stamps)) : null,
            ]),
        ];
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $headers = $encodedEnvelope['headers'] ?? [];
        $class = $headers['type'] ?? null;

        if ($class === null && isset($headers['alias'])) {
            $class = $this->registry?->classFor($headers['alias']);
        }

        if ($class === null || ! class_exists($class)) {
            throw new MessageDecodingFailedException('Encoded envelope does not have a valid "type" header.');
        }

        try {
            $data = json_decode($encodedEnvelope['body'] ?? '', true, 512, JSON_THROW_ON_ERROR);
            $message = $this->hydrator->hydrate($class, is_array($data) ? $data : []);
            $stamps = isset($headers['stamps']) ? unserialize(base64_decode($headers['stamps'], true)) : [];
        } catch (JsonException|Throwable $e) {
            throw new MessageDecodingFailedException('Could not decode message: '.$e->getMessage(), 0, $e);
        }

        return new Envelope($message, is_array($stamps) ? $stamps : []);
    }
}
