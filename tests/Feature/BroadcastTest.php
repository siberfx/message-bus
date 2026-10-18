<?php

namespace Siberfx\MessageBus\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Siberfx\MessageBus\Events\MessageBroadcasted;
use Siberfx\MessageBus\Facades\MessageBus;
use Siberfx\MessageBus\Realtime\Drivers\WebhookDriver;
use Siberfx\MessageBus\Stamp\BroadcastedStamp;
use Siberfx\MessageBus\Stamp\BroadcastStamp;
use Siberfx\MessageBus\Stamp\HttpOriginStamp;
use Siberfx\MessageBus\Stamp\NoBroadcastStamp;
use Siberfx\MessageBus\Stamp\UniqueIdStamp;
use Siberfx\MessageBus\Tests\Fixtures\Messages\ChatMessageSent;
use Siberfx\MessageBus\Tests\Fixtures\Messages\CounterIncremented;
use Siberfx\MessageBus\Tests\Fixtures\Messages\SendInvoice;
use Siberfx\MessageBus\Tests\TestCase;
use Siberfx\MessageBus\Worker\Worker;

class BroadcastTest extends TestCase
{
    public function test_attribute_messages_are_broadcast_after_handling(): void
    {
        $realtime = MessageBus::fakeRealtime();
        Event::fake([MessageBroadcasted::class]);

        $envelope = MessageBus::dispatch(new ChatMessageSent(5, 'Hello'), [new HttpOriginStamp(1, '123.456')]);

        $realtime->assertBroadcast('chat.message.sent', 'private-chat.5');
        $realtime->assertBroadcast('chat.message.sent', function (array $channels, array $payload) use ($envelope) {
            return $payload['id'] === $envelope->last(UniqueIdStamp::class)->id
                && $payload['type'] === 'chat.send'
                && $payload['data'] === ['roomId' => 5, 'body' => 'Hello']
                && $payload['result'] === ['stored' => true]
                && $payload['socket'] === '123.456';
        });

        $this->assertNotNull($envelope->last(BroadcastedStamp::class));
        Event::assertDispatched(MessageBroadcasted::class);
    }

    public function test_should_broadcast_live_messages(): void
    {
        $realtime = MessageBus::fakeRealtime();

        MessageBus::dispatch(new CounterIncremented(3));
        MessageBus::dispatch(new CounterIncremented(4, silent: true));

        $realtime->assertBroadcastTimes('counter.incremented', 1);
        $realtime->assertBroadcast('counter.incremented', fn ($channels, $payload) => $channels === ['counters'] && $payload['data'] === ['value' => 3]);
    }

    public function test_broadcast_stamps(): void
    {
        $realtime = MessageBus::fakeRealtime();

        MessageBus::dispatch(new SendInvoice(1), [new BroadcastStamp('invoices.{invoiceId}', 'invoice.queued')]);
        $realtime->assertNotBroadcast('invoice.queued');

        MessageBus::dispatch(new SendInvoice(2), [new BroadcastStamp('invoices.{invoiceId}', 'invoice.handled'), new \Siberfx\MessageBus\Stamp\TransportNamesStamp('sync')]);
        $realtime->assertBroadcast('invoice.handled', 'invoices.2');

        MessageBus::dispatch(new ChatMessageSent(1, 'quiet'), [new NoBroadcastStamp()]);
        $realtime->assertNotBroadcast('chat.message.sent');
    }

    public function test_async_messages_are_broadcast_when_consumed(): void
    {
        $realtime = MessageBus::fakeRealtime();

        MessageBus::dispatch(new SendInvoice(3), [new BroadcastStamp(['invoices'], 'invoice.sent')]);
        $realtime->assertNothingBroadcast();

        app(Worker::class)->run(['async'], ['stop_when_empty' => true, 'sleep' => 0]);

        $realtime->assertBroadcast('invoice.sent', 'invoices');
    }

    public function test_direct_publishing(): void
    {
        $realtime = MessageBus::fakeRealtime();

        MessageBus::publish('dashboard', 'stats.updated', ['users' => 10]);

        $realtime->assertBroadcast('stats.updated', fn ($channels, $payload) => $payload === ['users' => 10]);
    }

    public function test_webhook_driver_posts_signed_payloads(): void
    {
        Http::fake();

        config([
            'message-bus.realtime.default' => 'webhook',
            'message-bus.realtime.connections.webhook.url' => 'https://realtime.example.com/hook',
            'message-bus.realtime.connections.webhook.secret' => 's3cret',
        ]);

        MessageBus::dispatch(new ChatMessageSent(9, 'Hi'));

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $request->url() === 'https://realtime.example.com/hook'
                && $body['channels'] === ['private-chat.9']
                && $body['event'] === 'chat.message.sent'
                && $body['payload']['data']['body'] === 'Hi'
                && WebhookDriver::verify($request->body(), $request->header(WebhookDriver::SIGNATURE_HEADER)[0], 's3cret');
        });
    }

    public function test_broadcast_failures_are_reported_not_thrown(): void
    {
        Http::fake(['*' => Http::response('down', 500)]);

        config([
            'message-bus.realtime.default' => 'webhook',
            'message-bus.realtime.connections.webhook.url' => 'https://realtime.example.com/hook',
            'message-bus.realtime.connections.webhook.retries' => 1,
        ]);

        $envelope = MessageBus::dispatch(new ChatMessageSent(9, 'Hi'));

        $this->assertNull($envelope->last(BroadcastedStamp::class));
    }
}
