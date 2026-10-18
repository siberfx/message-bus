<?php

namespace App\Listeners;

use App\Notifications\MessageFailedNotification;
use Illuminate\Support\Facades\Notification;
use Siberfx\MessageBus\Events\MessageFailed;
use Siberfx\MessageBus\Stamp\RedeliveryStamp;

/**
 * Laravel auto-discovers this listener (app/Listeners, typed handle()).
 * MessageFailed fires for every failed attempt; $willRetry tells whether
 * the message was scheduled again or moved to the failure transport.
 */
final class AlertOnFailedMessage
{
    public function handle(MessageFailed $event): void
    {
        if ($event->willRetry) {
            logger()->warning('Message will be retried', [
                'message' => $event->envelope->getMessage()::class,
                'transport' => $event->transportName,
                'retry' => RedeliveryStamp::getRetryCountFromEnvelope($event->envelope) + 1,
                'error' => $event->exception->getMessage(),
            ]);

            return;
        }

        Notification::route('slack', config('services.slack.alerts_webhook'))
            ->notify(new MessageFailedNotification(
                message: $event->envelope->getMessage()::class,
                transport: $event->transportName,
                error: $event->exception->getMessage(),
            ));
    }
}
