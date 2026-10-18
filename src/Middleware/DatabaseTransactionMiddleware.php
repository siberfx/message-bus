<?php

namespace Siberfx\MessageBus\Middleware;

use Closure;
use Illuminate\Database\DatabaseManager;
use Siberfx\MessageBus\Contracts\MiddlewareInterface;
use Siberfx\MessageBus\Envelope;

/**
 * Wraps sending and handling in a database transaction. Place it in a bus'
 * middleware list; broadcasting happens after the transaction committed.
 */
class DatabaseTransactionMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected DatabaseManager $db,
        protected ?string $connection = null,
    ) {
    }

    public function handle(Envelope $envelope, Closure $next): Envelope
    {
        return $this->db->connection($this->connection)->transaction(fn () => $next($envelope));
    }
}
