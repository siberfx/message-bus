<?php

namespace App\Messages\Queries;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Siberfx\MessageBus\Attribute\AsMessage;
use Siberfx\MessageBus\Contracts\AuthorizesDispatch;

/**
 * Exposed to the frontend: bus.ask('orders.list', { customerId: 7 }).
 */
#[AsMessage(alias: 'orders.list', exposed: true, bus: 'query.bus')]
final class ListCustomerOrders implements AuthorizesDispatch
{
    public function __construct(
        public readonly int $customerId,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
        public readonly ?string $status = null,
    ) {
    }

    public function authorize(?Authenticatable $user, Request $request): bool
    {
        return $user !== null && ($user->customer_id === $this->customerId || $user->is_admin);
    }
}
