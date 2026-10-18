# 07 — Realtime with Laravel Reverb

Broadcastable messages are pushed to WebSocket channels **after they were handled** —
inline for synchronous messages, by the worker for async ones. Clients therefore only hear
about work that actually happened.

## Setup

```bash
php artisan install:broadcasting      # installs Reverb, laravel-echo & pusher-js, routes/channels.php
php artisan reverb:start
```

`.env` — see `.env.example` in this folder. The default realtime connection
`broadcast` uses `BROADCAST_CONNECTION`, so with `BROADCAST_CONNECTION=reverb` everything
goes through Reverb. Pusher, Ably and Redis (Soketi) work the same way.

## Files

| File | Purpose |
|------|---------|
| `app/Messages/PostComment.php` | `#[BroadcastOn]` with `{placeholders}`, `withResult`, `toOthers`. |
| `app/Messages/UpdateOrderStatus.php` | `ShouldBroadcastLive` with `broadcastWith/When/As/Via/ToOthers`. |
| `app/MessageHandlers/*.php` | Handlers. |
| `app/Http/Controllers/DashboardController.php` | `BroadcastStamp`, `NoBroadcastStamp`, `MessageBus::publish()`. |
| `routes/channels.php` | Authorization for private & presence channels. |
| `resources/js/echo.js` | Echo setup used by the React/Vue/Blade clients. |

## Three ways to make a message broadcastable

```php
// 1. Attribute — channels may use {placeholders} from public properties (dot notation ok)
#[BroadcastOn('private-posts.{postId}', as: 'comment.posted', withResult: true)]

// 2. Interface — full control in code
final class UpdateOrderStatus implements ShouldBroadcastLive { public function broadcastOn(): array { ... } }

// 3. Per dispatch — any message, overrides 1 and 2
MessageBus::dispatch($message, [new BroadcastStamp('private-admin', 'order.flagged')]);
```

Skip broadcasting for one dispatch with `new NoBroadcastStamp()`.

## Payload

Every broadcast uses the same envelope so frontends can treat events uniformly:

```json
{
  "id": "01jb3x...",                 // same id the HTTP endpoint returned → optimistic UI
  "type": "comments.post",           // message alias (or class name)
  "event": "comment.posted",
  "data": { "postId": 12, "body": "Nice!" },
  "result": { "id": 981, "author": "Ada" },   // only with withResult
  "sent_at": "2026-10-18T12:00:00+00:00"
}
```

## Excluding the sender

With `toOthers: true` (or `broadcastToOthers(): true`) and a message dispatched from the
browser client, the `X-Socket-ID` header is forwarded and Reverb/Pusher/Ably will not deliver
the event back to the tab that sent it.

## Failures

If publishing fails the exception is `report()`ed and handling still succeeds. Set
`realtime.throw => true` to make it fatal instead.
