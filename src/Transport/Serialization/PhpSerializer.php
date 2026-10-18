<?php

namespace Siberfx\MessageBus\Transport\Serialization;

use Siberfx\MessageBus\Contracts\SerializerInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Exception\MessageDecodingFailedException;
use Siberfx\MessageBus\Stamp\NonSendableStampInterface;
use Throwable;

/**
 * Serializes the whole envelope with PHP's native serialize(). Fast and
 * lossless, but only readable by PHP consumers.
 */
class PhpSerializer implements SerializerInterface
{
    public function encode(Envelope $envelope): array
    {
        $envelope = $envelope->withoutStampsOfType(NonSendableStampInterface::class);

        return [
            'body' => base64_encode(serialize($envelope)),
            'headers' => ['type' => $envelope->getMessage()::class, 'format' => 'php'],
        ];
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        if (empty($encodedEnvelope['body'])) {
            throw new MessageDecodingFailedException('Encoded envelope should have at least a "body".');
        }

        try {
            $envelope = unserialize((string) base64_decode($encodedEnvelope['body'], true));
        } catch (Throwable $e) {
            throw new MessageDecodingFailedException('Could not decode message: '.$e->getMessage(), 0, $e);
        }

        if (! $envelope instanceof Envelope) {
            throw new MessageDecodingFailedException('Could not decode message into an Envelope.');
        }

        return $envelope;
    }
}
