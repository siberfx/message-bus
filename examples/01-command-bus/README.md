# 01 — Command bus

A command expresses an *intention* ("place this order"). It has exactly one handler, which
changes state and may return a value (for example the id of the created record).

## Files

| File | Purpose |
|------|---------|
| `app/Messages/PlaceOrder.php` | The command, validated through `ValidatesPayload`. |
| `app/MessageHandlers/PlaceOrderHandler.php` | The single handler, discovered through `#[AsMessageHandler]`. |
| `app/Http/Controllers/OrderController.php` | Dispatches the command from a controller. |
| `config/message-bus.php` (excerpt) | Enables transactions on the command bus. |

## Generate the skeleton

```bash
php artisan make:message PlaceOrder --handler
```

## Dispatching

```php
use Siberfx\MessageBus\Facades\MessageBus;
use Siberfx\MessageBus\Stamp\HandledStamp;

$envelope = MessageBus::dispatch(new PlaceOrder(customerId: 1, items: [...]));

$order = $envelope->last(HandledStamp::class)?->result;

// or with the helper
message_bus(new PlaceOrder(...));
```

## Validation

`PlaceOrder` implements `ValidatesPayload`. The default `command.bus` runs the
`ValidationMiddleware`, so an invalid command throws an `Illuminate\Validation\ValidationException`
*before* it reaches the handler (and before it is sent to any async transport).
The same rules are used by the HTTP endpoint when the frontend dispatches the message.

## Transactions

Add `DatabaseTransactionMiddleware` to the bus to wrap handling in a DB transaction.
Broadcasting runs *outside* (after) your middleware, so realtime events are only sent
once the transaction committed. See `config/message-bus.php` in this folder.

## Exceptions

When a single handler throws during a synchronous dispatch, the original exception is
re-thrown (`unwrap_exceptions => true`). Your `ModelNotFoundException`,
`AuthorizationException` or `ValidationException` render exactly as they would in a controller.
