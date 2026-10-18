<?php

namespace Siberfx\MessageBus\Console\Concerns;

use Siberfx\MessageBus\Contracts\ListableReceiverInterface;
use Siberfx\MessageBus\Contracts\TransportInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Transport\TransportManager;

trait InteractsWithFailureTransport
{
    protected function failureTransportName(): ?string
    {
        return $this->option('transport') ?: config('message-bus.failure_transport');
    }

    protected function failureTransport(TransportManager $transports): (TransportInterface&ListableReceiverInterface)|null
    {
        $name = $this->failureTransportName();

        if ($name === null) {
            $this->components->error('No failure transport is configured.');

            return null;
        }

        $transport = $transports->get($name);

        if (! $transport instanceof ListableReceiverInterface) {
            $this->components->error(sprintf('The failure transport [%s] cannot list messages; use the database transport.', $name));

            return null;
        }

        return $transport;
    }

    /**
     * @return list<Envelope>
     */
    protected function selectedEnvelopes(ListableReceiverInterface $transport, array $ids, bool $all): array
    {
        if ($all) {
            return iterator_to_array((function () use ($transport) {
                yield from $transport->all();
            })(), false);
        }

        $envelopes = [];

        foreach ($ids as $id) {
            if ($envelope = $transport->find($id)) {
                $envelopes[] = $envelope;
            } else {
                $this->components->warn(sprintf('Message [%s] not found.', $id));
            }
        }

        return $envelopes;
    }
}
