<?php

namespace Siberfx\MessageBus\Exception;

use Throwable;

/**
 * Implement this on your own exceptions to always retry the message,
 * regardless of the configured maximum retries.
 */
interface RecoverableExceptionInterface extends Throwable
{
}
