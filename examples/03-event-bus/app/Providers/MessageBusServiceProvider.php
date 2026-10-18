<?php

namespace App\Providers;

use App\Messages\Events\OrderPlaced;
use App\Notifications\BigOrderNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Facades\MessageBus;

class MessageBusServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // A wildcard handler sees every message on the event bus.
        MessageBus::handle('*', function (object $message, Envelope $envelope) {
            app('metrics')->increment('events.'.class_basename($message));
        }, [
            'name' => 'event-metrics',  // closures need a stable name to be skipped on retries
            'bus' => 'event.bus',
            'priority' => -100,
        ]);

        // Closures are handy for small reactions that don't deserve a class.
        MessageBus::handle(OrderPlaced::class, function (OrderPlaced $event) {
            if ($event->totalInCents >= 100_000) {
                Notification::route('slack', config('services.slack.sales'))
                    ->notify(new BigOrderNotification($event->orderId));
            }
        }, ['name' => 'big-order-alert', 'bus' => 'event.bus']);

        // Invokable classes and "Class@method" strings work too:
        // MessageBus::handle(OrderPlaced::class, 'App\Listeners\Crm@orderPlaced', ['bus' => 'event.bus']);
    }
}
