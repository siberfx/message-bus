<?php

namespace Siberfx\MessageBus\Realtime\Drivers;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Arr;
use Siberfx\MessageBus\Contracts\RealtimeDriver;

/**
 * POSTs every event as signed JSON to an external service: your own Node
 * gateway, Mercure / Centrifugo bridges, serverless functions, Zapier...
 *
 * Body:    {"channels": [...], "event": "...", "payload": {...}, "sent_at": "..."}
 * Header:  X-Message-Bus-Signature: sha256=<hmac of the raw body with the secret>
 */
class WebhookDriver implements RealtimeDriver
{
    public const SIGNATURE_HEADER = 'X-Message-Bus-Signature';

    /**
     * @param  array{url?: string, secret?: string|null, headers?: array<string, string>, timeout?: int, retries?: int, retry_delay?: int}  $config
     */
    public function __construct(
        protected Factory $http,
        protected array $config,
    ) {
    }

    public function publish(array $channels, string $event, array $payload = []): void
    {
        unset($payload['socket']);

        $body = json_encode([
            'channels' => $channels,
            'event' => $event,
            'payload' => $payload,
            'sent_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $headers = (array) Arr::get($this->config, 'headers', []);

        if (! empty($this->config['secret'])) {
            $headers[self::SIGNATURE_HEADER] = 'sha256='.hash_hmac('sha256', $body, $this->config['secret']);
        }

        $this->http
            ->withHeaders($headers)
            ->timeout((int) ($this->config['timeout'] ?? 5))
            ->retry((int) ($this->config['retries'] ?? 1), (int) ($this->config['retry_delay'] ?? 100))
            ->withBody($body, 'application/json')
            ->post((string) $this->config['url'])
            ->throw();
    }

    /**
     * Verify a signature on the receiving side.
     */
    public static function verify(string $body, string $signature, string $secret): bool
    {
        return hash_equals('sha256='.hash_hmac('sha256', $body, $secret), $signature);
    }
}
