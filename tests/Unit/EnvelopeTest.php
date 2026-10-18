<?php

namespace Siberfx\MessageBus\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\BusNameStamp;
use Siberfx\MessageBus\Stamp\DelayStamp;
use Siberfx\MessageBus\Stamp\NonSendableStampInterface;
use Siberfx\MessageBus\Stamp\ReceivedStamp;
use Siberfx\MessageBus\Stamp\SentStamp;
use stdClass;

class EnvelopeTest extends TestCase
{
    public function test_it_is_immutable(): void
    {
        $envelope = new Envelope(new stdClass());
        $stamped = $envelope->with(new DelayStamp(1000));

        $this->assertNull($envelope->last(DelayStamp::class));
        $this->assertSame(1000, $stamped->last(DelayStamp::class)->delay);
    }

    public function test_last_returns_the_most_recent_stamp(): void
    {
        $envelope = Envelope::wrap(new stdClass(), [new BusNameStamp('a'), new BusNameStamp('b')]);

        $this->assertSame('b', $envelope->last(BusNameStamp::class)->busName);
        $this->assertCount(2, $envelope->all(BusNameStamp::class));
    }

    public function test_wrap_reuses_existing_envelopes(): void
    {
        $envelope = new Envelope(new stdClass(), [new BusNameStamp('a')]);
        $wrapped = Envelope::wrap($envelope, [new DelayStamp(5)]);

        $this->assertSame($envelope->getMessage(), $wrapped->getMessage());
        $this->assertNotNull($wrapped->last(BusNameStamp::class));
        $this->assertNotNull($wrapped->last(DelayStamp::class));
    }

    public function test_stamps_can_be_removed(): void
    {
        $envelope = Envelope::wrap(new stdClass(), [
            new ReceivedStamp('async'),
            new SentStamp('x', 'async'),
            new DelayStamp(10),
        ]);

        $this->assertNull($envelope->withoutAll(DelayStamp::class)->last(DelayStamp::class));

        $sendable = $envelope->withoutStampsOfType(NonSendableStampInterface::class);
        $this->assertNull($sendable->last(ReceivedStamp::class));
        $this->assertNull($sendable->last(SentStamp::class));
        $this->assertNotNull($sendable->last(DelayStamp::class));
    }

    public function test_envelopes_cannot_be_nested(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Envelope(new Envelope(new stdClass()));
    }
}
