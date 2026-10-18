# 03 — Event bus

Events describe something that *happened* ("order placed"). Any number of handlers may react
— including none. The `event.bus` is configured with `allow_no_handlers => true`.

## Files

| File | Purpose |
|------|---------|
| `app/Contracts/Auditable.php` | Marker interface; one handler reacts to every message implementing it. |
| `app/Messages/Events/OrderPlaced.php` | The event, bound to `event.bus` with `#[AsMessage(bus: ...)]`. |
| `app/MessageHandlers/OrderPlaced/*.php` | Three independent handlers with priorities. |
| `app/MessageHandlers/WriteAuditLog.php` | Handles every `Auditable` message (interface based). |
| `app/Providers/MessageBusServiceProvider.php` | Registers a `*` wildcard handler at runtime. |

## Dispatching

```php
// The bus comes from #[AsMessage(bus: 'event.bus')]
MessageBus::dispatch(new OrderPlaced($order->id, $order->customer_id, $order->total));

// or explicitly
MessageBus::bus('event.bus')->dispatch(new OrderPlaced(...));
```

## Handler order

Handlers run by **priority** (higher first). With the files in this folder:

```
ReserveStock (priority 100) → SendOrderConfirmation (0) → NotifyWarehouse (-10) → WriteAuditLog (-50) → wildcard metrics (-100)
```

If one handler throws, the others still run; afterwards a `HandlerFailedException`
(or the original exception when exactly one failed) is thrown. When the event is consumed
from an async transport and retried, handlers that already succeeded are **skipped**.

## Matching rules

A handler registered for… | receives
---|---
`OrderPlaced::class` | that class (and subclasses)
`Auditable::class` (interface) | every message implementing it
`'*'` | every message on every bus (combine with `bus` option to restrict)

Inspect the result with `php artisan message-bus:debug`.
