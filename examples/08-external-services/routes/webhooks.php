<?php

// A *receiving* Laravel application (e.g. an internal dashboard) that gets
// realtime events from another app through the webhook driver and re-publishes
// them on its own Reverb server.

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Siberfx\MessageBus\Facades\MessageBus;
use Siberfx\MessageBus\Realtime\Drivers\WebhookDriver;

Route::post('/webhooks/message-bus', function (Request $request) {
    $signature = (string) $request->header(WebhookDriver::SIGNATURE_HEADER);

    abort_unless(
        WebhookDriver::verify($request->getContent(), $signature, config('services.shop.webhook_secret')),
        401,
        'Invalid signature.',
    );

    $event = $request->json()->all();

    // Prefix the channels so they don't collide with this app's own channels.
    $channels = array_map(fn (string $channel) => 'shop.'.ltrim(str_replace(['private-', 'presence-'], '', $channel), '.'), $event['channels']);

    MessageBus::publish($channels, $event['event'], $event['payload'], connection: 'reverb');

    return response()->noContent();
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);
