<?php

namespace Siberfx\MessageBus\Contracts;

use Siberfx\MessageBus\Envelope;

interface TransportInterface
{
    /**
     * Send the envelope to the transport and return it with any stamps the
     * transport added (e.g. TransportMessageIdStamp).
     */
    public function send(Envelope $envelope): Envelope;

    /**
     * Receive pending envelopes. Pull based transports return envelopes;
     * push based transports (e.g. the Laravel queue) return nothing.
     *
     * @return iterable<Envelope>
     */
    public function get(): iterable;

    /**
     * Acknowledge that the envelope was handled successfully.
     */
    public function ack(Envelope $envelope): void;

    /**
     * Remove an envelope that could not be handled.
     */
    public function reject(Envelope $envelope): void;
}
