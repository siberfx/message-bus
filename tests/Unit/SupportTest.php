<?php

namespace Siberfx\MessageBus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Exception\HandlerFailedException;
use Siberfx\MessageBus\Exception\HydrationException;
use Siberfx\MessageBus\Exception\UnrecoverableMessageHandlingException;
use Siberfx\MessageBus\Livewire\LivewireEcho;
use Siberfx\MessageBus\Retry\MultiplierRetryStrategy;
use Siberfx\MessageBus\Stamp\RedeliveryStamp;
use Siberfx\MessageBus\Support\MessageHydrator;
use Siberfx\MessageBus\Support\Normalizer;
use Siberfx\MessageBus\Tests\Fixtures\Messages\Address;
use Siberfx\MessageBus\Tests\Fixtures\Messages\RegisterCustomer;
use Siberfx\MessageBus\Tests\Fixtures\Messages\Status;
use stdClass;

class SupportTest extends TestCase
{
    public function test_hydrator_builds_messages_from_arrays(): void
    {
        $message = (new MessageHydrator())->hydrate(RegisterCustomer::class, [
            'full_name' => 'Ada Lovelace',
            'age' => '36',
            'status' => 'banned',
            'address' => ['city' => 'London'],
            'registeredAt' => '2026-01-02 10:00:00',
            'newsletter' => 'true',
        ]);

        $this->assertSame('Ada Lovelace', $message->fullName);
        $this->assertSame(36, $message->age);
        $this->assertSame(Status::Banned, $message->status);
        $this->assertEquals(new Address('London'), $message->address);
        $this->assertSame('2026-01-02', $message->registeredAt->format('Y-m-d'));
        $this->assertTrue($message->newsletter);
        $this->assertSame([], $message->tags);
    }

    public function test_hydrator_reports_the_failing_field(): void
    {
        try {
            (new MessageHydrator())->hydrate(RegisterCustomer::class, ['full_name' => 'x', 'age' => 'old']);
            $this->fail('Expected a HydrationException.');
        } catch (HydrationException $e) {
            $this->assertSame('age', $e->field);
        }

        try {
            (new MessageHydrator())->hydrate(Address::class, []);
            $this->fail('Expected a HydrationException.');
        } catch (HydrationException $e) {
            $this->assertSame('city', $e->field);
            $this->assertSame('The city field is required.', $e->getMessage());
        }
    }

    public function test_normalizer_converts_objects_enums_and_dates(): void
    {
        $data = Normalizer::toArray(new RegisterCustomer(
            'Ada', 36, Status::Active, new Address('London', 'N1'), new \DateTimeImmutable('2026-01-02T00:00:00+00:00'),
        ));

        $this->assertSame('active', $data['status']);
        $this->assertSame(['city' => 'London', 'zip' => 'N1'], $data['address']);
        $this->assertSame('2026-01-02T00:00:00+00:00', $data['registeredAt']);
    }

    public function test_multiplier_retry_strategy(): void
    {
        $strategy = new MultiplierRetryStrategy(maxRetries: 2, delay: 100, multiplier: 3, maxDelay: 500);
        $envelope = new Envelope(new stdClass());

        $this->assertTrue($strategy->isRetryable($envelope));
        $this->assertSame(100, $strategy->getWaitingTime($envelope));
        $this->assertSame(300, $strategy->getWaitingTime($envelope->with(new RedeliveryStamp(1))));
        $this->assertSame(500, $strategy->getWaitingTime($envelope->with(new RedeliveryStamp(2))));
        $this->assertFalse($strategy->isRetryable($envelope->with(new RedeliveryStamp(2))));

        $unrecoverable = new HandlerFailedException($envelope, ['h' => new UnrecoverableMessageHandlingException('no')]);
        $this->assertFalse($strategy->isRetryable($envelope, $unrecoverable));

        $mixed = new HandlerFailedException($envelope, ['a' => new UnrecoverableMessageHandlingException('no'), 'b' => new RuntimeException('maybe')]);
        $this->assertTrue($strategy->isRetryable($envelope, $mixed));
    }

    public function test_livewire_echo_listener_names(): void
    {
        $this->assertSame('echo-private:orders.1,.order.shipped', LivewireEcho::listener('private-orders.1', 'order.shipped'));
        $this->assertSame('echo-presence:room.1,.joined', LivewireEcho::listener('presence-room.1', '.joined'));
        $this->assertSame('echo:news,.article.published', LivewireEcho::listener('news', 'article.published'));
    }
}
