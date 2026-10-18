<?php

namespace App\Exceptions;

use RuntimeException;
use Siberfx\MessageBus\Exception\RecoverableExceptionInterface;

/**
 * The gateway will come back: keep retrying, even past max_retries.
 */
final class PaymentGatewayDown extends RuntimeException implements RecoverableExceptionInterface
{
}
