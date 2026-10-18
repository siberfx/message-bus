# 06 — Retries & failures

When a consumed message fails, the bus asks the transport's **retry strategy** whether to
try again. Retried messages are re-sent to the same transport with a `RedeliveryStamp` and a
`DelayStamp`. Messages that can't be retried move to the **failure transport**, where you can
inspect, retry or delete them.

```
handler throws
   ├─ retryable?  → re-send with delay (1s, 2s, 4s, ... capped by max_delay)
   └─ no          → failure transport ("failed") + ErrorDetailsStamp
```

Handlers that succeeded during a failed attempt are **not** run again on retry.

## Files

| File | Purpose |
|------|---------|
| `config/message-bus.php` | Global and per transport retry policies. |
| `app/Exceptions/PaymentGatewayDown.php` | A *recoverable* exception: always retried. |
| `app/Exceptions/CardDeclined.php` | An *unrecoverable* exception: never retried. |
| `app/MessageHandlers/ChargeCustomerHandler.php` | Throws both kinds. |
| `app/Retry/BusinessHoursRetryStrategy.php` | Custom `RetryStrategyInterface`. |
| `app/Listeners/AlertOnFailedMessage.php` | Listens to `MessageFailed` and alerts Slack. |

## Exception types

| Throw… | Effect |
|---|---|
| any exception | retried until `max_retries`, then failure transport |
| `UnrecoverableMessageHandlingException` or your exception implementing `UnrecoverableExceptionInterface` | straight to the failure transport |
| `RecoverableMessageHandlingException` or `RecoverableExceptionInterface` | always retried (ignores `max_retries`) |

When several handlers failed, the message is unrecoverable only if *all* of them threw
unrecoverable exceptions.

## Working with failed messages

```bash
$ php artisan message-bus:failed
+----+---------------------------+--------------------+---------+---------------------------------+
| ID | Message                   | Original transport | Retries | Error                           |
+----+---------------------------+--------------------+---------+---------------------------------+
| 17 | App\Messages\ChargeCustomer | payments         | 3       | Card declined: insufficient...  |
| 18 | App\Messages\SyncCrmContact | async            | 3       | cURL error 28: Operation tim... |
+----+---------------------------+--------------------+---------+---------------------------------+

$ php artisan message-bus:failed 18          # details (+ trace with -v)
$ php artisan message-bus:failed:retry 18    # handle it again now
$ php artisan message-bus:failed:retry --all
$ php artisan message-bus:failed:remove 17 --force
```

Retrying dispatches the message as if it was received from its original transport, so
`fromTransport` handler restrictions still apply. If it fails again it stays in the failure
transport with the new error.

The failure transport must be listable — use the `database` (or `in-memory` in tests) driver.
