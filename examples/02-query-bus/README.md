# 02 — Query bus

A query *asks* for data and never changes state. It must have exactly one handler, and
`MessageBus::ask()` returns that handler's result directly.

## Files

| File | Purpose |
|------|---------|
| `app/Messages/Queries/GetOrderSummary.php` | Query routed to the `query.bus` through `#[AsMessage(bus: ...)]`. |
| `app/Messages/Queries/ListCustomerOrders.php` | A second query, exposed to the frontend. |
| `app/MessageHandlers/OrderQueries.php` | One class answering several queries (method level `#[AsMessageHandler]`). |
| `app/Http/Controllers/OrderSummaryController.php` | Uses `MessageBus::ask()`. |

## Asking

```php
use Siberfx\MessageBus\Facades\MessageBus;

$summary = MessageBus::ask(new GetOrderSummary(orderId: 42));

// Explicit bus
$summary = MessageBus::bus('query.bus')->ask(new GetOrderSummary(42));
```

`ask()` uses, in order: the `$bus` argument, `#[AsMessage(bus: ...)]` on the query, then
`default_query_bus` from the config (`query.bus`).

It throws a `LogicException` when the query was handled zero times (for example because
it is routed to an async transport) or more than once.

## From the frontend

Exposed queries can be asked over HTTP:

```js
const orders = await bus.ask('orders.list', { customerId: 7, page: 2 });
```

## Why `bus: 'query.bus'` on the handler?

`#[AsMessageHandler(bus: 'query.bus')]` restricts the handler to the query bus. If somebody
dispatches the query on the command bus by mistake, a `NoHandlerForMessageException` is
thrown instead of silently running it with the command bus middleware (transactions, ...).
