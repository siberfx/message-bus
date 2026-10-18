<?php

namespace Siberfx\MessageBus\Exception;

use Throwable;

/**
 * Implement this on your own exceptions to skip retries and send the
 * message straight to the failure transport.
 */
interface UnrecoverableExceptionInterface extends Throwable
{
}
