<?php

namespace App\Messages;

use Siberfx\MessageBus\Attribute\AsMessage;
use Siberfx\MessageBus\Contracts\ValidatesPayload;

/**
 * Command: place an order for a customer.
 *
 * "alias" gives the message a short public name, used by the HTTP endpoint
 * and in broadcast payloads. "exposed: true" lets the frontend dispatch it.
 */
#[AsMessage(alias: 'orders.place', exposed: true)]
final class PlaceOrder implements ValidatesPayload
{
    /**
     * @param  list<array{sku: string, quantity: int}>  $items
     */
    public function __construct(
        public readonly int $customerId,
        public readonly array $items,
        public readonly ?string $couponCode = null,
        public readonly ?string $note = null,
    ) {
    }

    public static function rules(): array
    {
        return [
            'customerId' => ['required', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['required', 'string', 'exists:products,sku'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'couponCode' => ['nullable', 'string', 'max:32'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
