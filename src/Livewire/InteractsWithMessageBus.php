<?php

namespace Siberfx\MessageBus\Livewire;

use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\MessageBusManager;
use Siberfx\MessageBus\Stamp\StampInterface;

/**
 * Use in Livewire components:
 *
 *     use InteractsWithMessageBus;
 *
 *     public function send(): void
 *     {
 *         $this->dispatchMessage(new SendChatMessage($this->roomId, $this->body));
 *     }
 *
 *     public function getListeners(): array
 *     {
 *         return [
 *             $this->busListener("private-chat.{$this->roomId}", 'chat.message.sent') => 'onMessage',
 *         ];
 *     }
 *
 * Note: Livewire already has a dispatch() method (browser events), hence
 * dispatchMessage() / askMessage().
 */
trait InteractsWithMessageBus
{
    /**
     * @param  StampInterface[]  $stamps
     */
    protected function dispatchMessage(object $message, array $stamps = [], ?string $bus = null): Envelope
    {
        return $this->messageBus()->dispatch($message, $stamps, $bus);
    }

    /**
     * @param  StampInterface[]  $stamps
     */
    protected function askMessage(object $query, array $stamps = [], ?string $bus = null): mixed
    {
        return $this->messageBus()->ask($query, $stamps, $bus);
    }

    /**
     * Build a Livewire Echo listener key for a message bus channel & event.
     */
    protected function busListener(string $channel, string $event): string
    {
        return LivewireEcho::listener($channel, $event);
    }

    protected function messageBus(): MessageBusManager
    {
        return app(MessageBusManager::class);
    }
}
