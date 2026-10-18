<?php

namespace Siberfx\MessageBus\Testing;

use Closure;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;
use Siberfx\MessageBus\Contracts\MessageBusInterface;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\MessageBusManager;
use Siberfx\MessageBus\Stamp\BusNameStamp;
use Siberfx\MessageBus\Stamp\HandledStamp;
use Siberfx\MessageBus\Stamp\UniqueIdStamp;

/**
 * Records dispatched messages instead of handling them.
 *
 *     $bus = MessageBus::fake();                    // fake everything
 *     $bus = MessageBus::fake([PlaceOrder::class]); // fake only these, dispatch the rest
 *     $bus->respond(GetOrder::class, fn ($query) => ['id' => $query->id]);
 */
class MessageBusFake
{
    /**
     * @var list<Envelope>
     */
    protected array $dispatched = [];

    /**
     * @var array<class-string, mixed>
     */
    protected array $responses = [];

    /**
     * @param  list<class-string>  $only
     */
    public function __construct(
        protected MessageBusManager $manager,
        protected array $only = [],
    ) {
    }

    public function bus(string $name): MessageBusInterface
    {
        return new FakeBus($name, $this, fn () => $this->manager->realBus($name));
    }

    public function shouldFake(object $message): bool
    {
        if ($this->only === []) {
            return true;
        }

        foreach ($this->only as $type) {
            if ($message instanceof $type) {
                return true;
            }
        }

        return false;
    }

    public function record(Envelope $envelope): Envelope
    {
        if ($envelope->last(UniqueIdStamp::class) === null) {
            $envelope = $envelope->with(new UniqueIdStamp());
        }

        foreach ($this->responses as $type => $response) {
            if ($envelope->getMessage() instanceof $type) {
                $result = $response instanceof Closure ? $response($envelope->getMessage(), $envelope) : $response;
                $envelope = $envelope->with(new HandledStamp($result, 'fake'));
                break;
            }
        }

        $this->dispatched[] = $envelope;

        return $envelope;
    }

    /**
     * Define what ask() returns for a faked message type.
     */
    public function respond(string $messageType, mixed $response): static
    {
        $this->responses[$messageType] = $response;

        return $this;
    }

    /**
     * @param  (Closure(object, Envelope): bool)|int|null  $callback
     */
    public function assertDispatched(string $messageType, Closure|int|null $callback = null): static
    {
        if (is_int($callback)) {
            return $this->assertDispatchedTimes($messageType, $callback);
        }

        PHPUnit::assertTrue(
            $this->dispatched($messageType, $callback)->isNotEmpty(),
            sprintf('The expected [%s] message was not dispatched.', $messageType),
        );

        return $this;
    }

    public function assertDispatchedTimes(string $messageType, int $times = 1): static
    {
        $count = $this->dispatched($messageType)->count();

        PHPUnit::assertSame($times, $count, sprintf('The expected [%s] message was dispatched %d times instead of %d times.', $messageType, $count, $times));

        return $this;
    }

    public function assertDispatchedOn(string $bus, string $messageType, ?Closure $callback = null): static
    {
        PHPUnit::assertNotEmpty(
            array_filter(
                $this->envelopes($messageType, $callback),
                fn (Envelope $envelope) => $envelope->last(BusNameStamp::class)?->busName === $bus,
            ),
            sprintf('The expected [%s] message was not dispatched on bus [%s].', $messageType, $bus),
        );

        return $this;
    }

    public function assertNotDispatched(string $messageType, ?Closure $callback = null): static
    {
        PHPUnit::assertTrue(
            $this->dispatched($messageType, $callback)->isEmpty(),
            sprintf('The unexpected [%s] message was dispatched.', $messageType),
        );

        return $this;
    }

    public function assertNothingDispatched(): static
    {
        PHPUnit::assertEmpty($this->dispatched, 'Messages were dispatched unexpectedly.');

        return $this;
    }

    /**
     * @return Collection<int, object>
     */
    public function dispatched(string $messageType, ?Closure $callback = null): Collection
    {
        return collect($this->envelopes($messageType, $callback))->map(fn (Envelope $envelope) => $envelope->getMessage());
    }

    /**
     * @return list<Envelope>
     */
    public function envelopes(?string $messageType = null, ?Closure $callback = null): array
    {
        return array_values(array_filter($this->dispatched, function (Envelope $envelope) use ($messageType, $callback) {
            $message = $envelope->getMessage();

            return ($messageType === null || $message instanceof $messageType)
                && ($callback === null || $callback($message, $envelope));
        }));
    }
}
