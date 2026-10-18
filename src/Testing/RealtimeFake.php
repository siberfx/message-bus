<?php

namespace Siberfx\MessageBus\Testing;

use Closure;
use PHPUnit\Framework\Assert as PHPUnit;
use Siberfx\MessageBus\Contracts\RealtimeDriver;

/**
 * Records realtime events instead of publishing them.
 *
 *     $realtime = MessageBus::fakeRealtime();
 *     MessageBus::dispatch(new SendChatMessage(...));
 *     $realtime->assertBroadcast('chat.message.sent', fn ($channels, $payload) => $payload['data']['body'] === 'Hi');
 */
class RealtimeFake implements RealtimeDriver
{
    /**
     * @var list<array{channels: list<string>, event: string, payload: array<string, mixed>}>
     */
    protected array $published = [];

    public function publish(array $channels, string $event, array $payload = []): void
    {
        $this->published[] = compact('channels', 'event', 'payload');
    }

    /**
     * @param  (Closure(list<string>, array<string, mixed>): bool)|string|null  $callback  A callback, or a channel name.
     */
    public function assertBroadcast(string $event, Closure|string|null $callback = null): static
    {
        PHPUnit::assertNotEmpty(
            $this->broadcasts($event, $callback),
            sprintf('The expected realtime event [%s] was not broadcast%s.', $event, is_string($callback) ? " on [{$callback}]" : ''),
        );

        return $this;
    }

    public function assertBroadcastTimes(string $event, int $times = 1): static
    {
        $count = count($this->broadcasts($event));

        PHPUnit::assertSame($times, $count, sprintf('The realtime event [%s] was broadcast %d times instead of %d times.', $event, $count, $times));

        return $this;
    }

    public function assertNotBroadcast(string $event, Closure|string|null $callback = null): static
    {
        PHPUnit::assertEmpty($this->broadcasts($event, $callback), sprintf('The unexpected realtime event [%s] was broadcast.', $event));

        return $this;
    }

    public function assertNothingBroadcast(): static
    {
        PHPUnit::assertEmpty($this->published, 'Realtime events were broadcast unexpectedly.');

        return $this;
    }

    /**
     * @return list<array{channels: list<string>, event: string, payload: array<string, mixed>}>
     */
    public function broadcasts(?string $event = null, Closure|string|null $callback = null): array
    {
        return array_values(array_filter($this->published, function (array $broadcast) use ($event, $callback) {
            if ($event !== null && $broadcast['event'] !== $event) {
                return false;
            }

            if (is_string($callback)) {
                return in_array($callback, $broadcast['channels'], true);
            }

            return $callback === null || $callback($broadcast['channels'], $broadcast['payload']);
        }));
    }
}
