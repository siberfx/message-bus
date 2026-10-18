<?php

namespace Siberfx\MessageBus\Contracts;

use Siberfx\MessageBus\Envelope;

interface SerializerInterface
{
    /**
     * @return array{body: string, headers: array<string, mixed>}
     */
    public function encode(Envelope $envelope): array;

    /**
     * @param  array{body: string, headers?: array<string, mixed>}  $encodedEnvelope
     */
    public function decode(array $encodedEnvelope): Envelope;
}
