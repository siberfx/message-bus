<?php

namespace App\Realtime;

use Illuminate\Http\Client\Factory as Http;
use Siberfx\MessageBus\Contracts\RealtimeDriver;

/**
 * Publishes to Centrifugo (https://centrifugal.dev) with its HTTP API.
 *
 * Laravel style "private-orders.1" channels are mapped to Centrifugo
 * namespaces: "private:orders.1".
 */
final class CentrifugoDriver implements RealtimeDriver
{
    public function __construct(
        private readonly Http $http,
        private readonly string $url,
        private readonly string $apiKey,
    ) {
    }

    public function publish(array $channels, string $event, array $payload = []): void
    {
        unset($payload['socket']);

        $this->http
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->post(rtrim($this->url, '/').'/broadcast', [
                'channels' => array_map($this->channel(...), $channels),
                'data' => ['event' => $event, 'payload' => $payload],
            ])
            ->throw();
    }

    private function channel(string $channel): string
    {
        return match (true) {
            str_starts_with($channel, 'private-') => 'private:'.substr($channel, 8),
            str_starts_with($channel, 'presence-') => 'presence:'.substr($channel, 9),
            default => $channel,
        };
    }
}
