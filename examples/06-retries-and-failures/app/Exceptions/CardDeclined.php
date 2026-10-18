<?php

namespace App\Exceptions;

use RuntimeException;
use Siberfx\MessageBus\Exception\UnrecoverableExceptionInterface;

/**
 * Retrying a declined card is pointless: fail immediately.
 */
final class CardDeclined extends RuntimeException implements UnrecoverableExceptionInterface
{
}
