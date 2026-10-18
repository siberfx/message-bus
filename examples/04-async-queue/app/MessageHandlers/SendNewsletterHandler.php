<?php

namespace App\MessageHandlers;

use App\Mail\NewsletterMail;
use App\Messages\SendNewsletter;
use App\Models\Campaign;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Mail;
use Siberfx\MessageBus\Attribute\AsMessageHandler;
use Siberfx\MessageBus\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final class SendNewsletterHandler
{
    public function __invoke(SendNewsletter $message): void
    {
        $subscriber = Subscriber::find($message->subscriberId);

        if ($subscriber === null || $subscriber->unsubscribed_at !== null) {
            // Retrying will never help: skip retries, go to the failure transport.
            throw new UnrecoverableMessageHandlingException("Subscriber {$message->subscriberId} is gone.");
        }

        Mail::to($subscriber->email)->send(new NewsletterMail(Campaign::findOrFail($message->campaignId), $subscriber));
    }
}
