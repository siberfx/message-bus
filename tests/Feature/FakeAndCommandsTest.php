<?php

namespace Siberfx\MessageBus\Tests\Feature;

use Illuminate\Support\Facades\File;
use Siberfx\MessageBus\Facades\MessageBus;
use Siberfx\MessageBus\Tests\Fixtures\Messages\CreateUser;
use Siberfx\MessageBus\Tests\Fixtures\Messages\GetGreeting;
use Siberfx\MessageBus\Tests\Fixtures\Messages\OrderPlaced;
use Siberfx\MessageBus\Tests\Fixtures\Recorder;
use Siberfx\MessageBus\Tests\TestCase;

class FakeAndCommandsTest extends TestCase
{
    public function test_the_bus_can_be_faked(): void
    {
        $bus = MessageBus::fake();
        $bus->respond(GetGreeting::class, fn (GetGreeting $query) => 'Fake '.$query->name);

        MessageBus::dispatch(new CreateUser('Ada', 'ada@example.com'));
        MessageBus::bus('event.bus')->dispatch(new OrderPlaced(1));

        $this->assertSame('Fake Bob', MessageBus::ask(new GetGreeting('Bob')));
        $this->assertSame([], Recorder::$calls);

        $bus->assertDispatched(CreateUser::class, fn (CreateUser $message) => $message->name === 'Ada');
        $bus->assertDispatchedTimes(CreateUser::class, 1);
        $bus->assertDispatchedOn('event.bus', OrderPlaced::class);
        $bus->assertNotDispatched(CreateUser::class, fn (CreateUser $message) => $message->name === 'Bob');
    }

    public function test_partial_fakes_dispatch_other_messages(): void
    {
        $bus = MessageBus::fake([OrderPlaced::class]);

        MessageBus::dispatch(new CreateUser('Ada', 'ada@example.com'));
        MessageBus::bus('event.bus')->dispatch(new OrderPlaced(1));

        $this->assertSame(['create:Ada'], Recorder::$calls);
        $bus->assertDispatched(OrderPlaced::class);
        $bus->assertNotDispatched(CreateUser::class);
    }

    public function test_debug_and_stats_commands(): void
    {
        $this->artisan('message-bus:debug')
            ->expectsOutputToContain('command.bus')
            ->expectsOutputToContain('greeting.get')
            ->assertSuccessful();

        $this->artisan('message-bus:stats')->assertSuccessful();
    }

    public function test_discovery_can_be_cached(): void
    {
        $path = sys_get_temp_dir().'/message-bus-discovery-'.uniqid().'.php';
        config(['message-bus.discovery.cache' => $path]);

        $this->artisan('message-bus:cache')->assertSuccessful();
        $this->assertFileExists($path);
        $this->assertNotEmpty((require $path)['handlers']);

        $this->artisan('message-bus:clear')->assertSuccessful();
        $this->assertFileDoesNotExist($path);
    }

    public function test_generators(): void
    {
        $this->artisan('make:message', ['name' => 'ShipOrder', '--handler' => true, '--async' => true, '--exposed' => true])->assertSuccessful();

        $message = app_path('Messages/ShipOrder.php');
        $handler = app_path('MessageHandlers/ShipOrderHandler.php');

        try {
            $this->assertStringContainsString("#[AsMessage(transport: 'async', alias: 'ship.order', exposed: true)]", File::get($message));
            $this->assertStringContainsString('use App\Messages\ShipOrder;', File::get($handler));
            $this->assertStringContainsString('public function __invoke(ShipOrder $message)', File::get($handler));
        } finally {
            File::delete([$message, $handler]);
        }
    }
}
