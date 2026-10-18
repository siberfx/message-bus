<?php

namespace Siberfx\MessageBus\Tests\Feature;

use Illuminate\Validation\ValidationException;
use RuntimeException;
use Siberfx\MessageBus\Exception\HandlerFailedException;
use Siberfx\MessageBus\Exception\LogicException;
use Siberfx\MessageBus\Exception\NoHandlerForMessageException;
use Siberfx\MessageBus\Facades\MessageBus;
use Siberfx\MessageBus\MessageBusManager;
use Siberfx\MessageBus\Stamp\BusNameStamp;
use Siberfx\MessageBus\Stamp\HandledStamp;
use Siberfx\MessageBus\Stamp\UniqueIdStamp;
use Siberfx\MessageBus\Tests\Fixtures\Messages\CreateUser;
use Siberfx\MessageBus\Tests\Fixtures\Messages\GetGreeting;
use Siberfx\MessageBus\Tests\Fixtures\Messages\OrderPlaced;
use Siberfx\MessageBus\Tests\Fixtures\Messages\UnhandledMessage;
use Siberfx\MessageBus\Tests\Fixtures\Recorder;
use Siberfx\MessageBus\Tests\Fixtures\StampingMiddleware;
use Siberfx\MessageBus\Tests\TestCase;

class BusTest extends TestCase
{
    public function test_it_dispatches_to_discovered_handlers(): void
    {
        $envelope = MessageBus::dispatch(new CreateUser('Ada', 'ada@example.com'));

        $this->assertSame(['create:Ada'], Recorder::$calls);
        $this->assertSame('command.bus', $envelope->last(BusNameStamp::class)->busName);
        $this->assertNotNull($envelope->last(UniqueIdStamp::class));
        $this->assertSame('Ada', $envelope->last(HandledStamp::class)->result['name']);
    }

    public function test_ask_returns_the_handler_result_on_the_message_bus(): void
    {
        $this->assertSame('Hello Grace', MessageBus::ask(new GetGreeting('Grace')));
        $this->assertSame('Hello Linus', message_bus()->bus('query.bus')->ask(new GetGreeting('Linus')));
    }

    public function test_handlers_restricted_to_a_bus_are_skipped_on_other_buses(): void
    {
        $this->expectException(NoHandlerForMessageException::class);

        MessageBus::bus('command.bus')->dispatch(new GetGreeting('Grace'));
    }

    public function test_ask_requires_exactly_one_handler(): void
    {
        $this->expectException(LogicException::class);

        MessageBus::bus('event.bus')->ask(new OrderPlaced(1));
    }

    public function test_handlers_run_by_priority(): void
    {
        MessageBus::bus('event.bus')->dispatch(new OrderPlaced(7));

        $this->assertSame(['stock:7', 'email:7'], Recorder::$calls);
    }

    public function test_missing_handlers_throw_unless_allowed(): void
    {
        MessageBus::bus('event.bus')->dispatch(new UnhandledMessage());

        $this->expectException(NoHandlerForMessageException::class);
        MessageBus::dispatch(new UnhandledMessage());
    }

    public function test_handlers_can_be_registered_at_runtime(): void
    {
        MessageBus::handle(UnhandledMessage::class, fn (UnhandledMessage $message) => 'closure');
        MessageBus::handle('*', fn () => 'wildcard', ['name' => 'wildcard', 'priority' => -100]);

        $handled = MessageBus::dispatch(new UnhandledMessage())->all(HandledStamp::class);

        $this->assertSame(['closure', 'wildcard'], array_map(fn ($stamp) => $stamp->result, $handled));
    }

    public function test_original_exceptions_are_rethrown_on_sync_dispatch(): void
    {
        try {
            MessageBus::dispatch(new CreateUser('explode', 'x@example.com'));
            $this->fail('Expected an exception.');
        } catch (RuntimeException $e) {
            $this->assertSame('Boom', $e->getMessage());
        }

        config(['message-bus.unwrap_exceptions' => false]);
        app(MessageBusManager::class)->forgetBuses();

        $this->expectException(HandlerFailedException::class);
        MessageBus::dispatch(new CreateUser('explode', 'x@example.com'));
    }

    public function test_validation_middleware_validates_messages(): void
    {
        $this->expectException(ValidationException::class);

        MessageBus::dispatch(new CreateUser('Ada', 'not-an-email'));
    }

    public function test_custom_middleware_wraps_handling(): void
    {
        config(['message-bus.buses' => array_replace(config('message-bus.buses'), [
            'command.bus' => ['middleware' => [StampingMiddleware::class]],
        ])]);
        app(MessageBusManager::class)->forgetBuses();

        MessageBus::dispatch(new CreateUser('Ada', 'ada@example.com'));

        $this->assertSame(['middleware:before', 'create:Ada', 'middleware:after'], Recorder::$calls);
    }
}
