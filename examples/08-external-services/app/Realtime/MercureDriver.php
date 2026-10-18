<?php

namespace App\Realtime;

use Illuminate\Http\Client\Factory as Http;
use Siberfx\MessageBus\Contracts\RealtimeDriver;

/**
 * Publishes to a Mercure hub (https://mercure.rocks). Each channel becomes a
 * topic; private/presence channels are published as private updates.
 */
final class MercureDriver implements RealtimeDriver
{
    public function __construct(
        private readonly Http $http,
        private readonly string $hub,
        private readonly string $jwt,
        private readonly string $topicPrefix = '',
    ) {
    }

    public function publish(array $channels, string $event, array $payload = []): void
    {
        unset($payload['socket']);

        foreach ($channels as $channel) {
            $private = str_starts_with($channel, 'private-') || str_starts_with($channel, 'presence-');

            $this->http
                ->withToken($this->jwt)
                ->asForm()
                ->post($this->hub, array_filter([
                    'topic' => $this->topicPrefix.preg_replace('/^(private|presence)-/', '', $channel),
                    'type' => $event,
                    'data' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'private' => $private ? 'on' : null,
                ]))
                ->throw();
        }
    }
}
