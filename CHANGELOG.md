# Changelog

All notable changes to `siberfx/message-bus` will be documented in this file.

## v0.1.0 - 2026-10-18

- README: new "Use cases" section with real-world scenarios (checkout side effects, live chat, long-running exports, unreliable third-party APIs, scheduled follow-ups, live dashboards, multi-tenancy, non-PHP consumers).
- README: release and license badges now read from GitHub.

## v0.0.1 - 2026-10-18

Initial release. Requires PHP 8.3+ and Laravel 13.

- Message buses (command / query / event) with a middleware pipeline, envelopes and stamps.
- Attribute based handler discovery (`#[AsMessageHandler]`, `#[AsMessage]`) with caching, config and runtime registration.
- Transports: sync, Laravel queue, database (with `message-bus:consume` worker), in-memory, custom drivers.
- Retries with exponential back-off, failure transport and `message-bus:failed*` commands.
- PHP and JSON serializers, `MessageHydrator`.
- Realtime broadcasting via Laravel broadcasting (Reverb, Pusher, Ably, Redis), signed webhooks, log, null and custom drivers.
- HTTP endpoint for exposed messages with validation and authorization.
- Zero-build JS client, React hooks, Vue composables, Alpine helpers, Blade components, Livewire trait.
- Testing helpers: `MessageBus::fake()`, `MessageBus::fakeRealtime()`.
