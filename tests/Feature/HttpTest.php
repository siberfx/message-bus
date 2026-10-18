<?php

namespace Siberfx\MessageBus\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Blade;
use Siberfx\MessageBus\Facades\MessageBus;
use Siberfx\MessageBus\Tests\Fixtures\Messages\GetGreeting;
use Siberfx\MessageBus\Tests\Fixtures\Recorder;
use Siberfx\MessageBus\Tests\TestCase;

class HttpTest extends TestCase
{
    public function test_exposed_queries_return_their_result(): void
    {
        $this->postJson('/message-bus/dispatch', ['message' => 'greeting.get', 'payload' => ['name' => 'Ada']])
            ->assertOk()
            ->assertJson(['message' => 'greeting.get', 'handled' => true, 'sent' => [], 'result' => 'Hello Ada'])
            ->assertJsonStructure(['id']);
    }

    public function test_commands_are_validated_and_hydrated(): void
    {
        $this->postJson('/message-bus/dispatch', ['message' => 'user.create', 'payload' => ['name' => 'Ada']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->postJson('/message-bus/dispatch', ['message' => 'user.create', 'payload' => ['name' => 'Ada', 'email' => 'ada@example.com', 'status' => 'nope']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->postJson('/message-bus/dispatch', ['message' => 'user.create', 'payload' => ['name' => 'Ada', 'email' => 'ada@example.com', 'status' => 'banned']])
            ->assertOk()
            ->assertJsonPath('result.status', 'banned');

        $this->assertSame(['create:Ada'], Recorder::$calls);
    }

    public function test_async_messages_are_accepted(): void
    {
        $this->postJson('/message-bus/dispatch', ['message' => 'invoice.send', 'payload' => ['invoice_id' => 3]])
            ->assertStatus(202)
            ->assertJson(['handled' => false, 'sent' => ['async']]);
    }

    public function test_unknown_and_unexposed_messages_are_rejected(): void
    {
        $this->postJson('/message-bus/dispatch', ['message' => 'nope'])->assertNotFound();
        $this->postJson('/message-bus/dispatch', ['message' => 'internal'])->assertNotFound();
        $this->postJson('/message-bus/dispatch', [])->assertStatus(422);
    }

    public function test_messages_can_authorize_dispatches(): void
    {
        $this->postJson('/message-bus/dispatch', ['message' => 'secret'])->assertForbidden();

        $this->actingAs(new User())
            ->postJson('/message-bus/dispatch', ['message' => 'secret'])
            ->assertOk()
            ->assertJsonPath('result', 'revealed');
    }

    public function test_messages_can_be_exposed_at_runtime(): void
    {
        MessageBus::expose('hello', GetGreeting::class);

        $this->postJson('/message-bus/dispatch', ['message' => 'hello', 'payload' => ['name' => 'Bob']])
            ->assertOk()
            ->assertJsonPath('result', 'Hello Bob');
    }

    public function test_the_browser_client_is_served(): void
    {
        $this->get('/message-bus/js/core.js')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=utf-8');

        $this->get('/message-bus/js/react.js')->assertNotFound();
    }

    public function test_blade_renders_the_script_tags(): void
    {
        $html = str_replace('\\', '', Blade::render('@messageBusScripts'));

        $this->assertStringContainsString('window.MessageBusConfig', $html);
        $this->assertStringContainsString('/message-bus/dispatch', $html);
        $this->assertStringContainsString('/message-bus/js/blade.js', $html);

        $component = Blade::render('<x-message-bus::listener channel="orders" event="order.placed">x</x-message-bus::listener>');
        $this->assertStringContainsString('busListener(', $component);

        $form = Blade::render('<x-message-bus::form message="chat.send" :payload="[\'room_id\' => 1]"><input name="body"></x-message-bus::form>');
        $this->assertStringContainsString('busDispatch(', $form);
        $this->assertStringContainsString('submit.prevent', $form);
    }
}
