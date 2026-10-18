# 09 — Custom middleware

Middleware wrap every dispatch on a bus. They receive the `Envelope` and a `$next` closure —
the same shape as Laravel HTTP middleware:

```php
public function handle(Envelope $envelope, Closure $next): Envelope
{
    // before
    $envelope = $next($envelope);
    // after (the envelope now carries HandledStamp / SentStamp ...)
    return $envelope;
}
```

The full pipeline of a bus:

```
PrepareEnvelope → [Logging] → Broadcast → <your middleware> → SendMessage → HandleMessage
```

So your middleware runs **before** a message is sent to a transport *and* again when the
worker receives it (check `ReceivedStamp` to tell both apart).

## Files

| File | Purpose |
|------|---------|
| `app/Messaging/Stamps/TenantStamp.php` | A custom stamp — travels with the message through transports. |
| `app/Messaging/Middleware/TenantContextMiddleware.php` | Captures the tenant + locale at dispatch, restores them in the worker. |
| `app/Messaging/Middleware/AuthorizeMiddleware.php` | Calls a policy for messages implementing `RequiresPermission`. |
| `app/Messaging/Middleware/MetricsMiddleware.php` | Times handling, counts failures. |
| `config/message-bus.php` | Registers middleware per bus. |

## Registering

```php
'buses' => [
    'command.bus' => [
        'middleware' => [
            TenantContextMiddleware::class,
            AuthorizeMiddleware::class,
            ValidationMiddleware::class,
            MetricsMiddleware::class,
            DatabaseTransactionMiddleware::class,
        ],
    ],
],
```

Middleware are resolved from the container once per bus, so constructor injection works.
Keep them stateless — the same instance serves every dispatch.

Run `php artisan message-bus:debug` to print each bus' final stack.

## Custom stamps

Any class implementing `Siberfx\MessageBus\Stamp\StampInterface` can be attached at
dispatch time or by middleware. Stamps are serialized together with the message, so they are
available in the worker. Implement `NonSendableStampInterface` instead for process-local data
that must not be sent to transports.
