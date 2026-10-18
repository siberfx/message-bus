<?php

namespace App\Messages;

/**
 * No attribute needed: config('message-bus.routing') sends it to "newsletters".
 */
final class SendNewsletter
{
    public function __construct(
        public readonly int $campaignId,
        public readonly int $subscriberId,
    ) {
    }
}
