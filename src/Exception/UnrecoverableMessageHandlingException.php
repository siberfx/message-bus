<?php

namespace Siberfx\MessageBus\Exception;

/**
 * Throw from a handler to make the message fail without being retried.
 */
class UnrecoverableMessageHandlingException extends RuntimeException implements UnrecoverableExceptionInterface
{
}
