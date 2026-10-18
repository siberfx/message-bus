# 08 — External realtime services

Realtime delivery is pluggable. Out of the box:

| Driver | Use it for |
|--------|-----------|
| `broadcast` | Any Laravel broadcasting connection: **Reverb, Pusher, Ably, Redis/Soketi**, log, null |
| `webhook` | POST a signed JSON payload to **any HTTP service** (your Node gateway, serverless function, Zapier, ...) |
| `log` / `null` | Development & tests |
| your own | `MessageBus::extendRealtime('name', fn ($config, $app) => new Driver(...))` |

## Files

| File | Purpose |
|------|---------|
| `config/message-bus.php` | Realtime connections: Pusher, Ably, webhook, Centrifugo, Mercure. |
| `node-gateway/server.mjs` | Tiny Express + WebSocket gateway receiving the webhook and fanning out to browsers. |
| `routes/webhooks.php` | A Laravel app receiving webhooks from another Laravel app (signature check). |
| `app/Realtime/CentrifugoDriver.php` | Custom driver for Centrifugo's HTTP API. |
| `app/Realtime/MercureDriver.php` | Custom driver for a Mercure hub. |
| `app/Providers/RealtimeServiceProvider.php` | Registers both custom drivers. |

## Choosing a connection

```php
// globally
MESSAGE_BUS_REALTIME=webhook

// per message
#[BroadcastOn('orders', via: 'centrifugo')]

// per dispatch
new BroadcastStamp('orders', 'order.created', via: 'pusher');

// direct publish
MessageBus::publish('orders', 'order.created', ['id' => 1], connection: 'ably');
MessageBus::realtime('mercure')->publish(['orders'], 'order.created', ['id' => 1]);
```

## Webhook format

```http
POST https://realtime.example.com/hook
Content-Type: application/json
X-Message-Bus-Signature: sha256=8f1c...

{"channels":["private-chat.1"],"event":"chat.message.sent","payload":{...},"sent_at":"2026-10-18T12:00:00+00:00"}
```

The signature is `HMAC-SHA256(raw body, secret)`. Verify it on the receiving side with
`Siberfx\MessageBus\Realtime\Drivers\WebhookDriver::verify($body, $signature, $secret)` in PHP,
or with `crypto.createHmac` in Node (see `node-gateway/server.mjs`). Failed requests are
retried (`retries`, `retry_delay`) and then reported.
