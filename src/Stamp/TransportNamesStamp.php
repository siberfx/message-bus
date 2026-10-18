<?php

namespace Siberfx\MessageBus\Stamp;

/**
 * Overrides the configured routing and sends the message to these transports.
 */
final class TransportNamesStamp implements StampInterface
{
    /**
     * @var list<string>
     */
    public readonly array $transportNames;

    /**
     * @param  string|list<string>  $transportNames
     */
    public function __construct(string|array $transportNames)
    {
        $this->transportNames = array_values((array) $transportNames);
    }
}
