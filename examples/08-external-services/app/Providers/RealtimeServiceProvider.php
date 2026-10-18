<?php

namespace App\Providers;

use App\Realtime\CentrifugoDriver;
use App\Realtime\MercureDriver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\ServiceProvider;
use Siberfx\MessageBus\Facades\MessageBus;

class RealtimeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The closure receives the connection's config array and the container.
        MessageBus::extendRealtime('centrifugo', fn (array $config, Container $app) => new CentrifugoDriver(
            $app->make(Http::class),
            $config['url'],
            (string) $config['key'],
        ));

        MessageBus::extendRealtime('mercure', fn (array $config, Container $app) => new MercureDriver(
            $app->make(Http::class),
            $config['hub'],
            (string) $config['jwt'],
            $config['topic_prefix'] ?? '',
        ));
    }
}
