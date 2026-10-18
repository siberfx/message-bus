# Message Bus for Laravel

[![Tests](https://github.com/siberfx/message-bus/actions/workflows/tests.yml/badge.svg)](https://github.com/siberfx/message-bus/actions/workflows/tests.yml)
[![Latest Release](https://img.shields.io/github/v/release/siberfx/message-bus.svg)](https://github.com/siberfx/message-bus/releases)
[![License](https://img.shields.io/github/license/siberfx/message-bus.svg)](LICENSE.md)

**`siberfx/message-bus`** is a [Symfony Messenger](https://symfony.com/doc/current/messenger.html) style message bus for Laravel, with realtime broadcasting and frontend integrations built in.

- **Buses and middleware.** Command, query and event buses, each running an envelope through a middleware pipeline.
- **Stamps.** Metadata that travels with a message: delay, routing overrides, retry count, error details and more.
- **Transports.** `sync`, any **Laravel queue** (Redis, SQS, database, Beanstalkd…), a dedicated **database** transport with its own worker, **in-memory** for tests, or your own.
- **Retries and a failure transport.** Retries back off exponentially. Failed messages can be listed, inspected, retried or removed from the CLI.
- **Realtime.** After a message is handled, it can be broadcast to **Reverb**, **Pusher**, **Ably**, Redis/Soketi, any **external service via signed webhooks**, or a driver you write.
- **Frontend.** **React** hooks, **Vue** composables, a **Livewire** trait, and **Blade components + Alpine** helpers. The core JS client has no dependencies and needs no build step.
- **Testing.** `MessageBus::fake()`, `MessageBus::fakeRealtime()`, the in-memory transport and the worker let you test async flows end to end.

```php
#[AsMessage(alias: 'chat.send', exposed: true)]
#[BroadcastOn('private-chat.{roomId}', as: 'chat.message.sent')]
final class SendChatMessage
{
    public function __construct(public readonly int $roomId, public readonly string $body) {}
}

#[AsMessageHandler]
final class SendChatMessageHandler
{
    public function __invoke(SendChatMessage $message): void
    {
        ChatMessage::create([...]);
    }
}
```

```jsx
const { dispatch } = useDispatch('chat.send');
const [messages] = useLiveList(`private-chat.${roomId}`, 'chat.message.sent');

await dispatch({ room_id: roomId, body: 'Hello!' }); // handled, then broadcast to everyone in the room
```

---

## Table of contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Core concepts](#core-concepts)
4. [Use cases](#use-cases)
5. [Quick start](#quick-start)
6. [Messages and handlers](#messages-and-handlers)
7. [Buses: commands, queries and events](#buses-commands-queries-and-events)
8. [Middleware](#middleware)
9. [Stamps](#stamps)
10. [Transports and routing](#transports-and-routing)
11. [Consuming messages (workers)](#consuming-messages-workers)
12. [Retries and the failure transport](#retries-and-the-failure-transport)
13. [Serialization](#serialization)
14. [Realtime broadcasting](#realtime-broadcasting)
15. [Dispatching from the frontend (HTTP endpoint)](#dispatching-from-the-frontend-http-endpoint)
16. [JavaScript client](#javascript-client)
17. [React](#react)
18. [Vue](#vue)
19. [Livewire](#livewire)
20. [Blade and Alpine.js](#blade-and-alpinejs)
21. [Testing](#testing)
22. [Events](#events)
23. [Artisan commands](#artisan-commands)
24. [Configuration reference](#configuration-reference)
25. [Coming from Symfony Messenger](#coming-from-symfony-messenger)
26. [Deployment checklist](#deployment-checklist)
27. [Examples](#examples)
28. [Security](#security)

---

## Requirements

| Package | Version |
| --- | --- |
| PHP | 8.3+ |
| Laravel | 13.x |
| Realtime (optional) | `laravel/reverb`, or Pusher / Ably / Redis broadcasting, or any HTTP endpoint |
| Frontend (optional) | `laravel-echo` + `pusher-js`, React 18+, Vue 3.3+, Livewire 3, Alpine 3 |

## Installation

```bash
composer require siberfx/message-bus
php artisan message-bus:install          # publishes config + migration, optionally migrates
```

The migration creates the `message_bus_messages` table. It is only needed by the `database` transport and the default `failed` transport.

For realtime with Reverb:

```bash
php artisan install:broadcasting         # installs Reverb + Laravel Echo
```

Publishable assets:

| Tag | What |
| --- | --- |
| `message-bus-config` | `config/message-bus.php` |
| `message-bus-migrations` | the `message_bus_messages` migration |
| `message-bus-stubs` | `stubs/message-bus/*.stub` for the generators |
| `message-bus-js` | the JS client into `resources/js/vendor/message-bus` |
| `message-bus-views` | the Blade components |

---

## Core concepts

```
                ┌──────────────────────────────── MessageBus ───────────────────────────────┐
dispatch(msg) ──▶ Prepare ─▶ [Logging] ─▶ Broadcast ─▶ your middleware… ─▶ Send ─▶ Handle ──▶ Envelope
                │  (bus name,                ▲                               │        │      │
                │   unique id)               │ after handling:                │        └─▶ handlers (HandledStamp)
                │                            │ push to Reverb/Pusher/…        ▼
                └────────────────────────────┼─────────────────────── transport (queue, database, …)
                                             │                               │
                                             └──────── worker ◀──────────────┘  (ReceivedStamp → handled → broadcast)
```

| Concept | What it is |
| --- | --- |
| **Message** | Any plain PHP object: a command (`PlaceOrder`), a query (`GetOrder`) or an event (`OrderPlaced`). |
| **Envelope** | Wraps the message together with its **stamps**. Immutable. |
| **Stamp** | A piece of metadata: `DelayStamp`, `TransportNamesStamp`, `HandledStamp`, `RedeliveryStamp`… |
| **Bus** | Runs envelopes through a middleware pipeline. You can define as many named buses as you need. |
| **Middleware** | `handle(Envelope $envelope, Closure $next): Envelope`, the same pattern as Laravel's HTTP middleware. |
| **Handler** | A callable that handles a message. Its return value is stored in a `HandledStamp`. |
| **Transport** | Where a routed message is sent: queue, database, in-memory or custom. |
| **Worker** | Consumes transports, handles messages, retries them or moves them to the failure transport. |
| **Realtime driver** | Pushes handled messages to channels: Laravel broadcasting, webhook, log, null or custom. |

Unrouted messages are handled **synchronously**, so you can start simple and move individual messages to async transports later without changing any calling code.

---

## Use cases

The scenarios below show which problems the package is built for and which features each one uses. Every API used here is documented in detail further down.

### Checkout with slow side effects

**Problem:** placing an order must be fast and transactional. Sending the receipt, notifying the warehouse and updating analytics should not slow down the response, and one failing listener must not lose the others.

**How:** `PlaceOrder` runs synchronously and returns the order. Its handler raises an `OrderPlaced` event on the event bus, and routing sends that event to a queue, where each listener runs and retries independently.

```php
// config/message-bus.php
'buses' => [
    'command.bus' => ['middleware' => [ValidationMiddleware::class, DatabaseTransactionMiddleware::class]],
],
'routing' => [
    App\Events\OrderPlaced::class => 'async',
],
```

```php
#[AsMessageHandler]
final class PlaceOrderHandler
{
    public function __invoke(PlaceOrder $command): Order
    {
        $order = Order::create([...]);

        MessageBus::bus('event.bus')->dispatch(new OrderPlaced($order->id));

        return $order;
    }
}

// In the controller: the order comes back immediately, listeners run on the worker.
$order = MessageBus::ask(new PlaceOrder($customerId, $items));
```

If `SendReceipt` fails, only `SendReceipt` is retried. Listeners that already succeeded are not run again.

### Live chat or comments

**Problem:** users post messages from the browser, and everyone else in the room should see them instantly, without writing a controller, a broadcast event and frontend wiring for each action.

**How:** one exposed message is the controller, the validator, the authorization check and the broadcast at once. The frontend dispatches it by alias and listens on the same channel.

```php
#[AsMessage(alias: 'chat.send', exposed: true)]
#[BroadcastOn('private-chat.{roomId}', as: 'chat.message.sent', toOthers: true)]
final class SendChatMessage implements ValidatesPayload, AuthorizesDispatch
{
    // constructor, rules() and authorize() as shown in "Dispatching from the frontend"
}
```

```js
await bus.dispatch('chat.send', { room_id: 5, body: 'Hi' });
bus.listen('private-chat.5', 'chat.message.sent', ({ data }) => append(data));
```

`toOthers` keeps the sender from receiving their own message twice, and the returned `id` matches the broadcast `id`, so optimistic UI updates can be reconciled. The same message works from [React](#react), [Vue](#vue), [Livewire](#livewire) or [Blade](#blade-and-alpinejs).

### Long-running exports and reports

**Problem:** generating a large CSV or PDF takes minutes. The request should return right away and the user should be told when the file is ready.

**How:** route the message to a worker and broadcast the handler's result when it finishes. Async messages are broadcast **when the worker handles them**, not when they are queued.

```php
#[AsMessage(alias: 'report.export', exposed: true, transport: 'database')]
#[BroadcastOn('private-user.{userId}', as: 'export.ready', withResult: true)]
final class ExportReport
{
    public function __construct(public readonly int $userId, public readonly string $period) {}
}

#[AsMessageHandler]
final class ExportReportHandler
{
    public function __invoke(ExportReport $message): array
    {
        $path = app(ReportBuilder::class)->build($message->period);

        return ['url' => Storage::temporaryUrl($path, now()->addHour())];
    }
}
```

```js
await bus.dispatch('report.export', { user_id: me.id, period: '2026-Q3' }); // 202 Accepted
bus.once(`private-user.${me.id}`, 'export.ready', ({ result }) => download(result.url));
```

### Calling unreliable third-party APIs

**Problem:** payment providers, SMS gateways and CRMs time out or rate limit you. Temporary failures should be retried, permanent ones should stop immediately, and nothing should be silently lost.

**How:** route the calls to a transport, let the worker retry with exponential back-off, and mark permanent errors as unrecoverable. Messages that keep failing land in the failure transport, where you can inspect and replay them.

```php
#[AsMessageHandler]
final class ChargeCustomerHandler
{
    public function __invoke(ChargeCustomer $message): void
    {
        try {
            $this->payments->charge($message->customerId, $message->amount);
        } catch (CardDeclined $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage()); // retrying will not help
        }
        // Timeouts and 5xx errors bubble up and are retried: 1s, 2s, 4s…
    }
}
```

```bash
php artisan message-bus:failed            # what went wrong, and why
php artisan message-bus:failed:retry --all  # replay once the provider is back
```

### Scheduled follow-ups

**Problem:** send a reminder one day after signup, or expire a reservation after 15 minutes, without creating a scheduled job for each case.

**How:** dispatch the message with a `DelayStamp`. The transport holds it until it is due.

```php
MessageBus::dispatch(new ExpireReservation($reservation->id), [DelayStamp::seconds(15 * 60)]);
MessageBus::dispatch(new SendOnboardingTips($user->id), [DelayStamp::delayUntil(now()->addDay())]);
```

### Live operations dashboards

**Problem:** an admin dashboard should update as orders, stock levels and sign-ups change, but only for changes that matter.

**How:** messages that implement `ShouldBroadcastLive` decide their own channels, payload and conditions. For values that do not come from a message, publish directly.

```php
final class StockLevelChanged implements ShouldBroadcastLive
{
    public function broadcastOn(): array { return ['private-dashboard']; }
    public function broadcastWhen(): bool { return $this->qty < 10; } // only low-stock alerts
}

MessageBus::publish('private-dashboard', 'stats.updated', ['visitors' => $count]);
```

### Multi-tenant SaaS

**Problem:** a message dispatched for tenant A must be handled in tenant A's context, even when a shared worker picks it up minutes later.

**How:** a middleware stamps the tenant id on dispatch and restores it in the worker. The full code is in [Middleware](#middleware). The same pattern works for locale, the acting user or a request trace id.

### Feeding services that are not written in PHP

**Problem:** a Node gateway, a Go service or an automation tool needs to react to what happens in your Laravel app.

**How:** use the `json` serializer so other languages can read transport payloads, or the `webhook` realtime driver to POST every broadcast to an HMAC-signed endpoint.

```php
'transports' => [
    'integration' => ['driver' => 'database', 'serializer' => 'json'],
],
'realtime' => [
    'default' => 'webhook',
],
```

### When you do not need it

If your app only queues a few jobs and fires a few events, Laravel's built-in jobs, events and broadcasting are enough. This package pays off when you want one consistent way to dispatch commands, queries and events from both PHP and the browser, decide sync or async per message in config, and get retries, failure handling and realtime updates without writing them for every feature.

---

## Quick start

```bash
php artisan make:message PlaceOrder --handler
```

```php
// app/Messages/PlaceOrder.php
namespace App\Messages;

use Siberfx\MessageBus\Attribute\AsMessage;

#[AsMessage(alias: 'order.place')]
final class PlaceOrder
{
    public function __construct(
        public readonly int $customerId,
        public readonly array $items,
    ) {}
}
```

```php
// app/MessageHandlers/PlaceOrderHandler.php
namespace App\MessageHandlers;

use App\Messages\PlaceOrder;
use App\Models\Order;
use Siberfx\MessageBus\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PlaceOrderHandler
{
    public function __construct(private readonly InventoryService $inventory) {} // resolved from the container

    public function __invoke(PlaceOrder $message): Order
    {
        $this->inventory->reserve($message->items);

        return Order::create([...]);
    }
}
```

```php
use Siberfx\MessageBus\Facades\MessageBus;
use Siberfx\MessageBus\Stamp\HandledStamp;

$envelope = MessageBus::dispatch(new PlaceOrder(customerId: 1, items: [...]));
$order = $envelope->last(HandledStamp::class)->result;

// or simply
$order = MessageBus::ask(new PlaceOrder(...));

// helper
message_bus(new PlaceOrder(...));
```

To make it async, route it to a transport and run a worker:

```php
// config/message-bus.php
'routing' => [
    App\Messages\PlaceOrder::class => 'async',
],
```

```bash
php artisan queue:work --queue=messages
```

---

## Messages and handlers

### Registering handlers

Handlers are **discovered** from `#[AsMessageHandler]` attributes in the configured discovery paths (`app/Messages`, `app/MessageHandlers` by default). The handled message is inferred from the type of the first parameter.

```php
// Invokable class
#[AsMessageHandler]
final class SendWelcomeEmail
{
    public function __invoke(UserRegistered $event): void { ... }
}

// Several handlers in one class
final class OrderQueries
{
    #[AsMessageHandler(bus: 'query.bus')]
    public function find(FindOrder $query): ?Order { ... }

    #[AsMessageHandler(bus: 'query.bus')]
    public function list(ListOrders $query): LengthAwarePaginator { ... }
}

// Union types register the handler for each type
#[AsMessageHandler]
public function __invoke(OrderShipped|OrderDelivered $event): void { ... }
```

`#[AsMessageHandler]` options:

| Option | Description |
| --- | --- |
| `bus` | Only run on this bus (e.g. `'event.bus'`). |
| `fromTransport` | Only run when the message was received from this transport. |
| `handles` | Explicit message class, interface or `'*'`, instead of inferring it. |
| `method` | Method to call when the attribute is on the class (default `__invoke`). |
| `priority` | Higher runs first (default `0`). |

You can also register handlers **in config**:

```php
'handlers' => [
    App\Messages\PlaceOrder::class => App\MessageHandlers\PlaceOrderHandler::class,
    App\Events\OrderPlaced::class => [
        App\Listeners\SendReceipt::class,
        [App\Listeners\Warehouse::class, 'notify'],
        'App\Listeners\Analytics@track',
        ['handler' => App\Listeners\Slack::class, 'bus' => 'event.bus', 'priority' => -10],
    ],
],
```

or **at runtime**, for example in a service provider:

```php
MessageBus::handle(OrderPlaced::class, SendReceipt::class);
MessageBus::handle(OrderPlaced::class, fn (OrderPlaced $event) => Log::info('placed'), ['priority' => 5]);
MessageBus::handle('*', AuditEverything::class, ['bus' => 'event.bus']); // wildcard
MessageBus::handle(ShouldBeAudited::class, AuditHandler::class);        // any message implementing an interface
```

Handlers receive `($message, Envelope $envelope)`, so declare a second parameter if you need the stamps.

Discovery reflects over your files once per process. In production, run `php artisan message-bus:cache` on deploy and `message-bus:clear` to undo it.

### `#[AsMessage]`

```php
#[AsMessage(
    transport: 'async',       // default route when no config routing matches (string or array)
    alias: 'order.place',     // short public name: HTTP endpoint, broadcast "type", JSON serializer
    exposed: true,            // the frontend may dispatch it over HTTP
    bus: 'command.bus',       // bus used by MessageBus::dispatch() for this message
)]
```

---

## Buses: commands, queries and events

Three buses ship by default. You can add or rename them freely:

```php
'default_bus' => 'command.bus',
'default_query_bus' => 'query.bus',

'buses' => [
    'command.bus' => ['middleware' => [ValidationMiddleware::class], 'allow_no_handlers' => false],
    'query.bus'   => ['middleware' => [], 'allow_no_handlers' => false],
    'event.bus'   => ['middleware' => [], 'allow_no_handlers' => true], // events may have zero listeners
],
```

```php
MessageBus::dispatch(new PlaceOrder(...));                     // default bus or #[AsMessage(bus:)]
MessageBus::bus('event.bus')->dispatch(new OrderPlaced($id));  // a specific bus
$summary = MessageBus::ask(new GetOrderSummary($id));          // query: exactly one handler, returns its result
```

You can inject a bus as well: `MessageBusInterface` resolves to the default bus, and `MessageBusManager` gives you all of them.

`ask()` throws a `LogicException` when the message was handled zero times (for example because it was routed to an async transport) or more than once.

**Exceptions:** if a single handler fails during a synchronous dispatch, its **original exception** is re-thrown, so a `ValidationException` still renders a 422 and a `ModelNotFoundException` still renders a 404. If several handlers fail, or when the message is consumed by a worker, you get a `HandlerFailedException` that carries the envelope and every wrapped exception. Set `unwrap_exceptions` to `false` to always get the wrapper.

---

## Middleware

The pipeline for every bus is:

```
PrepareEnvelope → [Logging] → Broadcast → …your middleware… → SendMessage → HandleMessage
```

Built-in middleware you can add per bus:

| Middleware | Purpose |
| --- | --- |
| `ValidationMiddleware` | Validates messages implementing `ValidatesPayload` (`static rules()`) and throws `ValidationException`. Enabled on `command.bus` by default. |
| `DatabaseTransactionMiddleware` | Wraps sending and handling in a DB transaction. Broadcasting happens only after commit. |
| `LoggingMiddleware` | Enabled globally with `logging.enabled`. |

A custom middleware:

```php
use Closure;
use Siberfx\MessageBus\Contracts\MiddlewareInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\ReceivedStamp;

final class TenantMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, Closure $next): Envelope
    {
        if ($envelope->last(ReceivedStamp::class) === null) {
            // Dispatching: remember the current tenant.
            $envelope = $envelope->with(new TenantStamp(tenant()->id));
        } else {
            // Consuming in a worker: restore it.
            tenancy()->initialize($envelope->last(TenantStamp::class)->tenantId);
        }

        return $next($envelope);
    }
}
```

```php
'buses' => [
    'command.bus' => ['middleware' => [TenantMiddleware::class, ValidationMiddleware::class]],
],
```

Middleware classes are resolved from the container, so constructor injection works.

---

## Stamps

| Stamp | Added by | Meaning |
| --- | --- | --- |
| `BusNameStamp` | bus | Bus the message was dispatched on, so workers use the same bus. |
| `UniqueIdStamp` | bus | Stable ULID. Returned by the HTTP endpoint and included in broadcasts. |
| `DelayStamp(ms)` | you / retries | Delay delivery (`DelayStamp::seconds(30)`, `::delayUntil($date)`). |
| `TransportNamesStamp` | you | Override routing: `new TransportNamesStamp(['sync'])`. |
| `SentStamp` | send middleware | Sent to a transport. |
| `ReceivedStamp` | worker | Received from a transport. The message is handled, not re-sent. |
| `HandledStamp` | handle middleware | One per successful handler, with its `result`. |
| `TransportMessageIdStamp` | transports | Id assigned by the transport. |
| `RedeliveryStamp` | worker | Retry count. |
| `ErrorDetailsStamp` | worker | Exception class, message and trace of the last failure. |
| `SentToFailureTransportStamp` | worker | Original transport of a failed message. |
| `BroadcastStamp` | you | Broadcast this message even if it is not declared broadcastable. |
| `NoBroadcastStamp` | you | Never broadcast this dispatch. |
| `BroadcastedStamp` | broadcast middleware | Driver, channels and event it was broadcast with. |
| `HttpOriginStamp` | HTTP endpoint | User id, socket id and IP of a frontend dispatch. |

```php
MessageBus::dispatch(new SendReminder($id), [
    DelayStamp::seconds(3600),
    new TransportNamesStamp('database'),
]);
```

You can create your own stamps by implementing `StampInterface`. Implement `NonSendableStampInterface` for stamps that must not be serialized.

---

## Transports and routing

```php
'transports' => [
    'sync' => ['driver' => 'sync'],

    // Any Laravel queue connection. Consume with `queue:work`.
    'async' => ['driver' => 'queue', 'connection' => 'redis', 'queue' => 'messages'],

    // Stored in message_bus_messages. Consume with `message-bus:consume database`.
    'database' => [
        'driver' => 'database',
        'connection' => null,
        'table' => 'message_bus_messages',
        'queue' => 'default',
        'redeliver_timeout' => 3600,        // re-deliver if a worker died mid-message
        'retry' => ['max_retries' => 5],    // per-transport retry override
        'serializer' => 'json',             // per-transport serializer
        'failure_transport' => 'failed',    // per-transport failure transport (null disables)
    ],

    'failed' => ['driver' => 'database', 'queue' => 'failed'],

    // Great for tests. 'serialize' => true round-trips through the serializer.
    'memory' => ['driver' => 'in-memory', 'serialize' => true],
],
```

### Routing

When deciding where a message goes, the first match wins:

1. a `TransportNamesStamp` on the envelope;
2. the `routing` config or `MessageBus::route()`, matched by class, parent classes, interfaces and `'*'` (all matches are merged);
3. `#[AsMessage(transport: ...)]` on the message.

If none of these match, the message is handled synchronously.

```php
'routing' => [
    App\Messages\SendInvoice::class => 'async',
    App\Contracts\ShouldQueueMessage::class => 'async',   // every message implementing the interface
    App\Messages\ImportCsv::class => ['database', 'audit'], // several transports
],
```

Routing a message to a transport with the `sync` driver means "handle it now, in this process". This is useful together with another transport, such as `['sync', 'audit']`.

### Custom transports

```php
MessageBus::extendTransport('redis-stream', function (array $config, string $name, $app) {
    return new RedisStreamTransport($app['redis']->connection($config['connection'] ?? null), $config['stream']);
});
```

```php
'transports' => [
    'events' => ['driver' => 'redis-stream', 'stream' => 'domain-events'],
],
```

Implement `TransportInterface` (`send`, `get`, `ack`, `reject`). Optionally implement `MessageCountAwareInterface` to support `message-bus:stats`, and `ListableReceiverInterface` so the transport can be used as a failure transport. See [examples/10-custom-transport](examples/10-custom-transport).

---

## Consuming messages (workers)

| Transport driver | Consumed by |
| --- | --- |
| `queue` | `php artisan queue:work --queue=messages` (Horizon works too) |
| `database`, `in-memory`, custom pull transports | `php artisan message-bus:consume` |

```bash
php artisan message-bus:consume                     # all pull transports except the failure transport
php artisan message-bus:consume high default        # priority order: "high" is always drained first
php artisan message-bus:consume database --limit=1000 --time-limit=3600 --memory-limit=256
php artisan message-bus:consume database --stop-when-empty --sleep=500
php artisan message-bus:stop-workers                # graceful restart after deploy
```

Supervisor:

```ini
[program:message-bus]
command=php /var/www/artisan message-bus:consume database --time-limit=3600
autostart=true
autorestart=true
numprocs=2
stopwaitsecs=60
```

A consumed message is dispatched again on **its original bus** with a `ReceivedStamp`. Your middleware runs again and the handlers run, then realtime broadcasting happens.

---

## Retries and the failure transport

When a consumed message fails, it is:

- **retried** with exponential back-off (`delay × multiplier^retries`, capped by `max_delay`). Handlers that already succeeded are **not** run again;
- after `max_retries`, **moved to the failure transport** with an `ErrorDetailsStamp`.

```php
'retry' => ['max_retries' => 3, 'delay' => 1000, 'multiplier' => 2, 'max_delay' => 60000],
'failure_transport' => 'failed',
```

You can control retries from a handler:

```php
use Siberfx\MessageBus\Exception\UnrecoverableMessageHandlingException;
use Siberfx\MessageBus\Exception\RecoverableMessageHandlingException;

throw new UnrecoverableMessageHandlingException('Invalid IBAN');   // skip retries, straight to "failed"
throw new RecoverableMessageHandlingException('Rate limited');     // always retry
```

You can also mark your own exceptions with `UnrecoverableExceptionInterface` or `RecoverableExceptionInterface`. For a fully custom policy, implement `RetryStrategyInterface` and set `'retry' => ['strategy' => MyStrategy::class]`.

Managing failed messages:

```bash
php artisan message-bus:failed                 # list
php artisan message-bus:failed 42 -v           # details + trace
php artisan message-bus:failed:retry 42 43     # handle again
php artisan message-bus:failed:retry --all
php artisan message-bus:failed:remove 42 --force
```

---

## Serialization

| Serializer | Body | Use when |
| --- | --- | --- |
| `php` (default) | `serialize()`d envelope | Only PHP reads the transport. Lossless. |
| `json` | Message as JSON, plus `type` and `alias` headers | Other languages or tools need to read it, or you want readable payloads. |

The JSON serializer and the HTTP endpoint rebuild messages with the **`MessageHydrator`**. It:

- matches constructor parameters by name (`camelCase` or `snake_case`);
- casts scalars;
- builds **backed enums**, **dates** (`DateTimeImmutable`, Carbon) and **nested value objects**;
- resolves **Eloquent models** from their key.

When building fails, it throws a `HydrationException` that names the failing field.

---

## Realtime broadcasting

Once a message has been **handled**, it can be pushed to realtime channels. There are three ways to declare this.

**1. The `#[BroadcastOn]` attribute.** `{placeholders}` are filled from the message's public properties (dot notation works):

```php
#[BroadcastOn('private-orders.{orderId}', as: 'order.status.changed', withResult: true, toOthers: true)]
final class ChangeOrderStatus
{
    public function __construct(public readonly int $orderId, public readonly OrderStatus $status) {}
}
```

**2. The `ShouldBroadcastLive` interface**, for full control:

```php
final class StockLevelChanged implements ShouldBroadcastLive
{
    public function broadcastOn(): array { return ['inventory', "private-warehouse.{$this->warehouseId}"]; }
    public function broadcastAs(): string { return 'stock.changed'; }               // optional
    public function broadcastWith(): array { return ['sku' => $this->sku, 'qty' => $this->qty]; } // optional
    public function broadcastWhen(): bool { return $this->qty < 10; }                // optional
    public function broadcastVia(): ?string { return 'pusher'; }                     // optional connection
    public function broadcastToOthers(): bool { return true; }                       // optional
    public function broadcastWithResult(): bool { return false; }                    // optional
}
```

**3. A stamp, for a single dispatch:**

```php
MessageBus::dispatch($message, [new BroadcastStamp('private-user.'.$userId, 'export.ready', payload: ['url' => $url])]);
MessageBus::dispatch($message, [new NoBroadcastStamp()]);
```

**Broadcast directly**, without a message:

```php
MessageBus::publish('dashboard', 'stats.updated', ['visitors' => 42]);
MessageBus::realtime('webhook')->publish(['audit'], 'user.deleted', ['id' => 7]);
```

### Payload shape

Every message broadcast has the same shape, so frontends can rely on it:

```json
{
  "id": "01jc7k8r1q0m4w3x6e9s2v5b7n",
  "type": "chat.send",
  "event": "chat.message.sent",
  "data": { "roomId": 5, "body": "Hello" },
  "result": { "stored": true },
  "sent_at": "2026-10-18T12:00:00+00:00"
}
```

`id` is the same id the HTTP endpoint returned. Use it to reconcile optimistic UI updates. `result` is included only with `withResult`.

The default event name is the message alias, or the class name in dotted snake case (`SendChatMessage` → `send.chat.message`).

**Async messages are broadcast when a worker handles them, not when they are queued**, so clients only hear about work that is actually done.

### Realtime connections

```php
'realtime' => [
    'default' => env('MESSAGE_BUS_REALTIME', 'broadcast'),
    'connections' => [
        'broadcast' => ['driver' => 'broadcast', 'connection' => env('BROADCAST_CONNECTION')], // reverb, pusher, ably, redis, log
        'reverb'    => ['driver' => 'broadcast', 'connection' => 'reverb'],
        'pusher'    => ['driver' => 'broadcast', 'connection' => 'pusher'],
        'ably'      => ['driver' => 'broadcast', 'connection' => 'ably'],
        'webhook'   => ['driver' => 'webhook', 'url' => env('MESSAGE_BUS_WEBHOOK_URL'), 'secret' => env('MESSAGE_BUS_WEBHOOK_SECRET')],
        'log'       => ['driver' => 'log'],
        'null'      => ['driver' => 'null'],
    ],
],
```

- **`broadcast`** goes through Laravel broadcasting, so **Reverb, Pusher, Ably, Redis (Soketi / laravel-echo-server)** all work with the usual `config/broadcasting.php`. Channel names follow Laravel's convention: `private-…`, `presence-…`, or plain for public channels. Authorize private channels in `routes/channels.php` as usual (without the prefix).
- **`webhook`** POSTs `{"channels", "event", "payload", "sent_at"}` to any URL, signed with `X-Message-Bus-Signature: sha256=<hmac>`. Use it to feed your own Node gateway, Centrifugo or Mercure bridges, serverless functions or automation tools. On the receiving side, verify the request with `WebhookDriver::verify($rawBody, $signature, $secret)`.
- **Custom drivers:**

```php
MessageBus::extendRealtime('mercure', fn (array $config, $app) => new MercureDriver($config['hub'], $config['jwt']));
```

Publishing failures are **reported, not thrown**, so a realtime outage never fails your command. Set `realtime.throw` to `true` to change this.

`toOthers` excludes the browser that dispatched the message (via its `X-Socket-ID`) on Reverb, Pusher and Ably.

---

## Dispatching from the frontend (HTTP endpoint)

Frontends dispatch messages **by alias** through `POST /message-bus/dispatch`. **Only exposed messages are accepted.**

```php
#[AsMessage(alias: 'chat.send', exposed: true)]
final class SendChatMessage implements ValidatesPayload, AuthorizesDispatch
{
    public function __construct(public readonly int $roomId, public readonly string $body) {}

    public static function rules(): array
    {
        return ['room_id' => ['required', 'integer'], 'body' => ['required', 'string', 'max:2000']];
    }

    public function authorize(?Authenticatable $user, Request $request): bool
    {
        return $user?->can('post', Room::find($this->roomId)) ?? false;
    }
}
```

You can also expose messages in config (`http.exposed`) or at runtime with `MessageBus::expose('chat.send', SendChatMessage::class)`.

The endpoint works in this order:

1. resolve the alias. Unknown or unexposed aliases return `404`;
2. validate with `rules()` (`422`);
3. hydrate the message (`422` with the failing field);
4. authorize with `authorize()` (`403`);
5. dispatch, adding an `HttpOriginStamp` (user id, `X-Socket-ID`, IP).

Request and response:

```http
POST /message-bus/dispatch
{"message": "chat.send", "payload": {"room_id": 5, "body": "Hello"}}

200 OK   {"id": "01jc…", "message": "chat.send", "handled": true,  "sent": [],        "result": {...}}
202 Accepted (routed async)   {"handled": false, "sent": ["async"], "result": null}
```

Settings in `config('message-bus.http')`: `enabled`, `prefix`, `middleware` (default `['web']`, which gives session auth and CSRF), `throttle` (default `120,1`).

---

## JavaScript client

The client is plain ES modules in `vendor/siberfx/message-bus/resources/js`. It has no dependencies and needs no build step. Realtime uses **Laravel Echo** (`window.Echo` by default).

**Use it with Vite through an alias:**

```js
// vite.config.js
import path from 'node:path';

export default defineConfig({
  resolve: {
    alias: {
      '@siberfx/message-bus': path.resolve(__dirname, 'vendor/siberfx/message-bus/resources/js'),
    },
  },
});
```

```js
import { createMessageBus } from '@siberfx/message-bus/core.js';
import { MessageBusProvider } from '@siberfx/message-bus/react.js';
import { MessageBusPlugin } from '@siberfx/message-bus/vue.js';
```

Alternatively, install it with npm, `npm i github:siberfx/message-bus` (exports `@siberfx/message-bus`, `/react`, `/vue`, `/alpine`), or publish the files with `php artisan vendor:publish --tag=message-bus-js`. TypeScript definitions are included.

```js
import { createMessageBus, MessageBusError } from '@siberfx/message-bus/core.js';

const bus = createMessageBus({ endpoint: '/message-bus/dispatch' }); // also: echo, headers, csrfToken, fetch

const { id, handled, result } = await bus.dispatch('chat.send', { room_id: 5, body: 'Hi' });
const summary = await bus.ask('order.summary', { id: 42 });

const stop = bus.listen('private-chat.5', 'chat.message.sent', (payload) => console.log(payload.data));
stop(); // the channel is left automatically when its last listener stops

bus.once('private-export.9', 'export.ready', ({ data }) => download(data.url));
bus.presence('room.5', { here, joining, leaving });
bus.whisper('private-chat.5', 'typing', { name: 'Ada' });
bus.onWhisper('private-chat.5', 'typing', (data) => {});
bus.on('error', ({ error }) => toast(error.message));     // also: dispatching, dispatched, message

try {
  await bus.dispatch('chat.send', {});
} catch (e) {
  if (e instanceof MessageBusError && e.isValidationError) console.log(e.errors.body);
}
```

The client sends the CSRF token from `<meta name="csrf-token">` or the `XSRF-TOKEN` cookie, and the Echo socket id as `X-Socket-ID`.

---

## React

```jsx
import { MessageBusProvider, useDispatch, useLiveList, useLiveQuery, usePresence, useWhisper, useLiveEvent } from '@siberfx/message-bus/react.js';

createRoot(el).render(
  <MessageBusProvider config={{ endpoint: '/message-bus/dispatch' }}>
    <ChatRoom roomId={5} />
  </MessageBusProvider>,
);

function ChatRoom({ roomId }) {
  const channel = `private-chat.${roomId}`;
  const { data: history } = useLiveQuery('chat.history', { room_id: roomId });
  const [messages] = useLiveList(channel, 'chat.message.sent', { max: 200 });
  const { dispatch, loading, errors } = useDispatch('chat.send');
  const members = usePresence(`room.${roomId}`);
  const whisperTyping = useWhisper(channel, 'typing', (data) => setTyping(data.name));

  useLiveEvent(channel, 'chat.room.closed', () => navigate('/'));

  return (
    <form onSubmit={(e) => { e.preventDefault(); dispatch({ room_id: roomId, body: e.target.body.value }); }}>
      {[...(history ?? []), ...messages.map((m) => m.data)].map((m) => <p key={m.id}>{m.body}</p>)}
      <input name="body" onInput={() => whisperTyping({ name: me.name })} />
      {errors.body && <small>{errors.body[0]}</small>}
      <button disabled={loading}>Send</button>
      <span>{members.length} online</span>
    </form>
  );
}
```

| Hook | Returns |
| --- | --- |
| `useMessageBus()` | the client |
| `useDispatch(alias)` | `{ dispatch, loading, error, errors, data, reset }` |
| `useLiveEvent(channel, event, handler)` | runs the handler for every event. Pass `null` as the channel to pause. |
| `useLiveQuery(alias, payload, { refreshOn, enabled, initialData })` | `{ data, loading, error, refetch }`. Refetches when `refreshOn: [[channel, event]]` fires. |
| `useLiveList(channel, event, { max, map, key, initial })` | `[items, setItems]`. Upserts by `key` (default `id`). |
| `usePresence(channel)` | members array |
| `useWhisper(channel, event, handler?)` | `whisper(data)` |

The module uses `createElement` rather than JSX, so it works straight from `vendor/`.

---

## Vue

```js
import { MessageBusPlugin } from '@siberfx/message-bus/vue.js';

createApp(App).use(MessageBusPlugin, { endpoint: '/message-bus/dispatch' }).mount('#app');
```

```vue
<script setup>
import { useDispatch, useLiveList, useLiveQuery, usePresence } from '@siberfx/message-bus/vue.js';

const props = defineProps({ orderId: Number });

const { data: order, refetch } = useLiveQuery('order.find', () => ({ id: props.orderId }), {
  refreshOn: () => [[`private-orders.${props.orderId}`, 'order.status.changed']],
});
const { dispatch, loading, errors } = useDispatch('order.cancel');
const events = useLiveList(() => `private-orders.${props.orderId}`, 'order.status.changed', { max: 20 });
const viewers = usePresence(() => `order.${props.orderId}`);
</script>

<template>
  <h1>#{{ order?.number }} — {{ order?.status }}</h1>
  <button :disabled="loading" @click="dispatch({ id: orderId })">Cancel</button>
  <p v-if="errors.id">{{ errors.id[0] }}</p>
  <li v-for="e in events" :key="e.id">{{ e.data.status }}</li>
  <small>{{ viewers.length }} people viewing</small>
</template>
```

The composables are `useMessageBus`, `useDispatch`, `useLiveEvent`, `useLiveQuery`, `useLiveList`, `usePresence` and `useWhisper`. Channels and payloads accept plain values, refs or getters, and re-subscribe when they change. `this.$bus` is available in the Options API.

---

## Livewire

```php
use Livewire\Component;
use Siberfx\MessageBus\Livewire\InteractsWithMessageBus;

class ChatRoom extends Component
{
    use InteractsWithMessageBus;

    public int $roomId;
    public string $body = '';
    public array $messages = [];

    public function send(): void
    {
        $this->dispatchMessage(new SendChatMessage($this->roomId, $this->body));
        $this->reset('body');
    }

    public function getListeners(): array
    {
        return [
            $this->busListener("private-chat.{$this->roomId}", 'chat.message.sent') => 'onMessage',
        ];
    }

    public function onMessage(array $payload): void
    {
        $this->messages[] = $payload['data'];
    }
}
```

- `dispatchMessage()` / `askMessage()` are named this way because Livewire already has `dispatch()` for browser events.
- `busListener()` converts `private-…` / `presence-…` names into Livewire's `echo-private:` / `echo-presence:` syntax. `LivewireEcho::listener()` does the same outside components.
- Add `@messageBusScripts` to the layout to also use the Alpine helpers (`$bus`, `busListener`) inside Livewire views.

---

## Blade and Alpine.js

Put this before `</body>`:

```blade
@messageBusScripts
{{-- or --}}
<x-message-bus::scripts />
```

This exposes `window.MessageBus` and registers the Alpine helpers. Registration hooks into Alpine's `alpine:init` event, so it also works with the Alpine bundled in Livewire 3.

```blade
{{-- Dispatch a message from a form without reloading the page --}}
<x-message-bus::form message="chat.send" :payload="['room_id' => $room->id]" class="flex gap-2">
    <input name="body" x-bind:disabled="loading">
    <button x-bind:disabled="loading">Send</button>
    <p x-show="errors.body" x-text="errors.body?.[0]"></p>
</x-message-bus::form>

{{-- Listen to realtime events --}}
<x-message-bus::listener channel="private-chat.{{ $room->id }}" event="chat.message.sent" :max="50">
    <template x-for="m in messages" :key="m.id">
        <p><strong x-text="m.data.author"></strong> <span x-text="m.data.body"></span></p>
    </template>
</x-message-bus::listener>

{{-- Or with Alpine directly --}}
<div x-data="busListener({ channel: 'orders', event: 'order.placed' })" @bus-message="new Audio('/ding.mp3').play()">
    <span x-text="count"></span> new orders
</div>

<button x-data @click="$bus.dispatch('cart.add', { sku: 'A-1' })">Add to cart</button>
```

You can also use the client from plain JavaScript: `window.MessageBus.dispatch(...)`, or listen for the `message-bus:ready` event.

**Zero-build Echo:** if your page doesn't set up Echo itself, set `MESSAGE_BUS_CLIENT_ECHO=true`. The scripts tag then loads `pusher-js` and `laravel-echo` from a CDN and configures them for Reverb from your `REVERB_*` variables.

---

## Testing

```php
use Siberfx\MessageBus\Facades\MessageBus;

public function test_checkout_places_an_order(): void
{
    $bus = MessageBus::fake();                                   // record instead of handle
    $bus->respond(GetCart::class, fn (GetCart $q) => ['total' => 100]);

    $this->post('/checkout')->assertRedirect();

    $bus->assertDispatched(PlaceOrder::class, fn (PlaceOrder $m) => $m->customerId === 1);
    $bus->assertDispatchedTimes(PlaceOrder::class, 1);
    $bus->assertDispatchedOn('event.bus', OrderPlaced::class);
    $bus->assertNotDispatched(RefundOrder::class);
}

public function test_partial_fake(): void
{
    $bus = MessageBus::fake([SendSms::class]); // only fake SendSms, handle everything else for real
}

public function test_order_status_is_broadcast(): void
{
    $realtime = MessageBus::fakeRealtime();

    MessageBus::dispatch(new ChangeOrderStatus(5, OrderStatus::Shipped));

    $realtime->assertBroadcast('order.status.changed', 'private-orders.5');
    $realtime->assertBroadcast('order.status.changed', fn (array $channels, array $payload) => $payload['data']['status'] === 'shipped');
}
```

**Testing async flows end to end:** use the in-memory transport and run the worker yourself:

```php
config(['message-bus.transports.async' => ['driver' => 'in-memory', 'serialize' => true]]);

MessageBus::dispatch(new SendInvoice(10));
$this->assertCount(1, MessageBus::transport('async')->getSent());

app(\Siberfx\MessageBus\Worker\Worker::class)->run(['async'], ['stop_when_empty' => true, 'sleep' => 0]);
$this->assertCount(1, MessageBus::transport('async')->getAcknowledged());
```

---

## Events

| Event | When |
| --- | --- |
| `MessageSent` | A message was sent to a transport. |
| `MessageHandled` | All handlers of a message succeeded. |
| `MessageFailed` | A consumed message failed. `$willRetry` tells you whether it will be retried. |
| `MessageBroadcasted` | A message was pushed to realtime channels. |
| `WorkerStarted` / `WorkerStopped` | Worker lifecycle. |

```php
Event::listen(MessageFailed::class, function (MessageFailed $event) {
    if (! $event->willRetry) {
        Notification::route('slack', config('services.slack.ops'))->notify(new MessageDeadLettered($event));
    }
});
```

---

## Artisan commands

| Command | Description |
| --- | --- |
| `message-bus:install` | Publish config and migration, optionally migrate. |
| `make:message {name} [--handler] [--async] [--exposed] [--broadcast]` | Generate a message (and handler). |
| `make:message-handler {name} [--message=]` | Generate a handler. |
| `message-bus:consume {transports?*}` | Run a worker (`--limit`, `--time-limit`, `--memory-limit`, `--sleep`, `--stop-when-empty`). |
| `message-bus:stop-workers` | Gracefully stop running workers. |
| `message-bus:failed {id?}` | List failed messages or show one. |
| `message-bus:failed:retry {id?*} [--all]` | Retry failed messages. |
| `message-bus:failed:remove {id?*} [--all] [--force]` | Delete failed messages. |
| `message-bus:debug` | Show buses, middleware, handlers, routing and aliases. |
| `message-bus:stats` | Pending messages per transport. |
| `message-bus:cache` / `message-bus:clear` | Cache or clear handler discovery. |

---

## Configuration reference

| Key | Default | Description |
| --- | --- | --- |
| `default_bus` | `command.bus` | Bus used by `MessageBus::dispatch()`. |
| `default_query_bus` | `query.bus` | Bus used by `MessageBus::ask()`. |
| `buses.*.middleware` | | Extra middleware per bus. |
| `buses.*.allow_no_handlers` | `false` | Accept messages without handlers. |
| `unwrap_exceptions` | `true` | Re-throw the original handler exception on sync dispatch. |
| `transports` | sync, async, database, failed | Transport definitions. |
| `routing` | `[]` | Message type → transport(s). |
| `handlers` | `[]` | Message type → handler(s). |
| `discovery.enabled` / `.paths` / `.cache` | `true` / `app/Messages`, `app/MessageHandlers` | Attribute discovery. |
| `retry` | 3 retries, 1s ×2, max 60s | Default retry strategy. |
| `failure_transport` | `failed` | Where messages go after the last retry. |
| `serializer` | `php` | `php`, `json` or a class name. |
| `realtime.enabled` / `.default` / `.throw` / `.connections` | `true` / `broadcast` / `false` | Realtime settings. |
| `http.enabled` / `.prefix` / `.middleware` / `.throttle` / `.exposed` | `true` / `message-bus` / `['web']` / `120,1` / `[]` | Frontend endpoint. |
| `client.echo` | disabled | Zero-build Echo for Blade pages. |
| `logging.enabled` / `.channel` / `.level` | `false` | Log every message. |

Environment variables: `MESSAGE_BUS_DEFAULT`, `MESSAGE_BUS_QUEUE_CONNECTION`, `MESSAGE_BUS_QUEUE`, `MESSAGE_BUS_DB_CONNECTION`, `MESSAGE_BUS_FAILURE_TRANSPORT`, `MESSAGE_BUS_SERIALIZER`, `MESSAGE_BUS_REALTIME`, `MESSAGE_BUS_WEBHOOK_URL`, `MESSAGE_BUS_WEBHOOK_SECRET`, `MESSAGE_BUS_CLIENT_ECHO`, `MESSAGE_BUS_LOGGING`, `MESSAGE_BUS_LOG_CHANNEL`.

---

## Coming from Symfony Messenger

| Symfony Messenger | Message Bus for Laravel |
| --- | --- |
| `MessageBusInterface::dispatch()` | `MessageBus::dispatch()` / `MessageBusInterface` |
| `HandleTrait::handle()` | `MessageBus::ask()` |
| `#[AsMessageHandler]` | `#[AsMessageHandler]` (same options: `bus`, `fromTransport`, `handles`, `method`, `priority`) |
| `#[AsMessage(transport:)]` | `#[AsMessage(transport:, alias:, exposed:, bus:)]` |
| `framework.messenger.routing` | `config('message-bus.routing')` |
| Envelope / stamps | Same model: `Envelope::with()`, `last()`, `all()` |
| `MiddlewareInterface` + `StackInterface` | `MiddlewareInterface::handle(Envelope, Closure $next)` |
| `messenger:consume` | `message-bus:consume` (or `queue:work` for queue transports) |
| `messenger:failed:show/retry/remove` | `message-bus:failed`, `:failed:retry`, `:failed:remove` |
| Doctrine transport | `database` transport |
| `sync://`, `in-memory://` | `sync`, `in-memory` drivers |
| Mercure integration | Realtime layer: Reverb/Pusher/Ably/webhook/custom + React/Vue/Livewire/Blade clients |

---

## Deployment checklist

- [ ] `php artisan migrate` (the `message_bus_messages` table)
- [ ] `php artisan message-bus:cache` (handler discovery)
- [ ] `php artisan message-bus:stop-workers` and `php artisan queue:restart`
- [ ] Workers supervised: `queue:work --queue=messages` and/or `message-bus:consume`
- [ ] Reverb (or Pusher/Ably) running, and private channels authorized in `routes/channels.php`
- [ ] Every exposed message has `rules()` and, when needed, `authorize()`
- [ ] Alert on `MessageFailed` with `willRetry = false`, or monitor `message-bus:stats`

---

## Examples

The [`examples/`](examples) directory has complete, copy-pasteable examples: command, query and event buses, async queue and database workers, retries and failures, Reverb chat, external services and webhooks, custom middleware and transports, React, Vue, Livewire, Blade and Alpine, testing, and an end-to-end CQRS application.

---

## Security

- Only messages that are **explicitly exposed** can be dispatched over HTTP. Treat them like controller actions: validate with `rules()` and authorize with `authorize()`.
- The endpoint uses the `web` middleware group (session auth + CSRF) and is throttled by default.
- Webhook payloads are HMAC-SHA256 signed. Verify them with `WebhookDriver::verify()`.
- The `php` serializer unserializes data from your own transports. Do not point transports at storage that untrusted parties can write to. If you need that, use the `json` serializer.

If you discover a security issue, please email **info@siberfx.com** instead of opening an issue.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
