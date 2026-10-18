# 04 — Async messages on the Laravel queue

The `queue` transport driver pushes envelopes onto **any Laravel queue connection** —
Redis, database, SQS, Beanstalkd, or third party drivers such as Kafka or RabbitMQ.
Workers are plain `php artisan queue:work` processes; retries and the failure transport
are still handled by the message bus.

## Files

| File | Purpose |
|------|---------|
| `config/message-bus.php` | Transports and routing (excerpt). |
| `app/Messages/GenerateInvoicePdf.php` | Routed by attribute (`#[AsMessage(transport: 'async')]`). |
| `app/Messages/SendNewsletter.php` | Routed by config, on a separate, slower queue. |
| `app/MessageHandlers/*.php` | Handlers — identical to synchronous ones. |
| `app/Http/Controllers/InvoiceController.php` | `DelayStamp` and `TransportNamesStamp` usage. |
| `supervisor/message-bus-queue.conf` | Supervisor programs for the workers. |

## Routing priority

1. `TransportNamesStamp` passed at dispatch time
2. `routing` in `config/message-bus.php` (class, parent class, interface or `*`)
3. `#[AsMessage(transport: ...)]` on the message class
4. nothing matched → handled synchronously

Route to several transports at once (`['sync', 'audit']`) to handle inline *and* keep a copy.

## Running the workers

```bash
# messages transport (connection = QUEUE_CONNECTION, queue = "messages")
php artisan queue:work --queue=messages

# the newsletter transport uses its own queue on redis
php artisan queue:work redis --queue=newsletters --timeout=600
```

Each queued job is a `Siberfx\MessageBus\Transport\Queue\ConsumeEnvelopeJob`
(shown as `MessageBus: App\Messages\GenerateInvoicePdf` in Horizon). The job has `$tries = 1`:
retries are scheduled by the bus' retry strategy (with exponential delay) and messages that
keep failing end up in the failure transport — see example 06.

## Responses

A message that was only *sent* has no `HandledStamp`. Over HTTP the endpoint answers
`202 Accepted` with `{"handled": false, "sent": ["async"]}`. If it is broadcastable, the
realtime event is pushed by the worker once the handler finished.
