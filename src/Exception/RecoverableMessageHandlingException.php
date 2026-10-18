<?php

namespace Siberfx\MessageBus\Exception;

/**
 * Throw from a handler to force a retry, even past the retry limit.
 */
class RecoverableMessageHandlingException extends RuntimeException implements RecoverableExceptionInterface
{
}
