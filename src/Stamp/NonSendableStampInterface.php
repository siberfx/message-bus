<?php

namespace Siberfx\MessageBus\Stamp;

/**
 * Stamps implementing this interface are stripped before an envelope is
 * serialized and sent to a transport.
 */
interface NonSendableStampInterface extends StampInterface
{
}
