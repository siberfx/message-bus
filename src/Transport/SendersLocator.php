<?php

namespace Siberfx\MessageBus\Transport;

use Siberfx\MessageBus\Contracts\TransportInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Handler\HandlersLocator;
use Siberfx\MessageBus\Stamp\TransportNamesStamp;
use Siberfx\MessageBus\Support\MessageMetadata;

/**
 * Decides which transports a message is sent to. Priority:
 *  1. TransportNamesStamp on the envelope
 *  2. "routing" config / MessageBus::route() (class, parents, interfaces, "*")
 *  3. #[AsMessage(transport: ...)] on the message class
 */
class SendersLocator
{
    /**
     * @param  array<string, string|list<string>>  $routing
     */
    public function __construct(
        protected TransportManager $transports,
        protected array $routing = [],
    ) {
    }

    /**
     * @param  string|list<string>  $transports
     */
    public function route(string $messageType, string|array $transports): static
    {
        $this->routing[$messageType] = array_values((array) $transports);

        return $this;
    }

    /**
     * @return array<string, TransportInterface>
     */
    public function getSenders(Envelope $envelope): array
    {
        $senders = [];

        foreach ($this->getTransportNames($envelope) as $name) {
            $senders[$name] = $this->transports->get($name);
        }

        return $senders;
    }

    /**
     * @return list<string>
     */
    public function getTransportNames(Envelope $envelope): array
    {
        if (($stamp = $envelope->last(TransportNamesStamp::class)) !== null) {
            return array_values(array_unique($stamp->transportNames));
        }

        $names = [];

        foreach (HandlersLocator::listTypes($envelope) as $type) {
            if (isset($this->routing[$type])) {
                array_push($names, ...(array) $this->routing[$type]);
            }
        }

        if ($names === []) {
            $transport = MessageMetadata::asMessage($envelope->getMessage()::class)?->transport;
            $names = $transport !== null ? (array) $transport : [];
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function routing(): array
    {
        return $this->routing;
    }
}
