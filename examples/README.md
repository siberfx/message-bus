# Examples

Copy-pasteable examples for `siberfx/message-bus`. Every folder is self-contained and uses
regular application namespaces (`App\Messages`, `App\MessageHandlers`, ...), so you can drop
the files into a Laravel 13+ application.

| # | Example | What it shows |
|---|---------|---------------|
| 01 | [Command bus](01-command-bus) | A `PlaceOrder` command, its handler, validation and a controller dispatching it. |
| 02 | [Query bus](02-query-bus) | Queries answered with `MessageBus::ask()`, one handler class for many queries. |
| 03 | [Event bus](03-event-bus) | Domain events with several handlers, priorities, bus restriction, wildcard & interface handlers. |
| 04 | [Async (Laravel queue)](04-async-queue) | Routing messages to a queue, delays, per-dispatch transport overrides, Supervisor. |
| 05 | [Database transport & worker](05-database-transport-worker) | Pull based transport consumed by `message-bus:consume`, deploy hooks. |
| 06 | [Retries & failures](06-retries-and-failures) | Retry policies, unrecoverable errors, custom retry strategy, `failed:*` commands, alerts. |
| 07 | [Realtime with Reverb](07-realtime-reverb) | `#[BroadcastOn]`, `ShouldBroadcastLive`, broadcast stamps, channel authorization. |
| 08 | [External services](08-external-services) | Signed webhooks (Node & Laravel receivers), Pusher/Ably, a custom Centrifugo driver. |
| 09 | [Custom middleware](09-custom-middleware) | Tenant/locale context, timing metrics, custom stamps, per-bus registration. |
| 10 | [Custom transport](10-custom-transport) | A Redis Streams transport registered with `MessageBus::extendTransport()`. |
| 11 | [React](11-react) | Live chat: provider, `useLiveList`, `useDispatch`, `usePresence`, `useWhisper`, `useLiveQuery`. |
| 12 | [Vue](12-vue) | Live order dashboard with the Vue plugin and composables. |
| 13 | [Livewire](13-livewire) | `InteractsWithMessageBus`, Echo listeners and Alpine `$bus` inside Livewire views. |
| 14 | [Blade + Alpine](14-blade-alpine) | Zero-build pages: `@messageBusScripts`, `<x-message-bus::form>`, `busListener`. |
| 15 | [Testing](15-testing) | Fakes, realtime assertions, async flows with the in-memory transport, HTTP tests. |
| 16 | [CQRS full app](16-cqrs-full-app) | An end-to-end support ticket system tying everything together. |

## Conventions used in the examples

- Messages live in `app/Messages`, handlers in `app/MessageHandlers` — the default
  discovery paths, so `#[AsMessageHandler]` classes are picked up automatically.
- Channel names follow Laravel's convention: `private-…`, `presence-…` or plain public names.
- Broadcast payloads always look like:

```json
{
  "id": "01jabc...",
  "type": "chat.send",
  "event": "chat.message.sent",
  "data": { "roomId": 1, "body": "Hi" },
  "result": { "id": 42 },
  "sent_at": "2026-10-18T12:00:00+00:00"
}
```
