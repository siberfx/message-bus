<?php

namespace Siberfx\MessageBus\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Siberfx\MessageBus\Facades\MessageBus;
use Siberfx\MessageBus\Stamp\ErrorDetailsStamp;
use Siberfx\MessageBus\Stamp\HandledStamp;
use Siberfx\MessageBus\Stamp\RedeliveryStamp;
use Siberfx\MessageBus\Stamp\SentStamp;
use Siberfx\MessageBus\Stamp\SentToFailureTransportStamp;
use Siberfx\MessageBus\Stamp\TransportNamesStamp;
use Siberfx\MessageBus\Tests\Fixtures\Messages\AuditLog;
use Siberfx\MessageBus\Tests\Fixtures\Messages\FlakyMessage;
use Siberfx\MessageBus\Tests\Fixtures\Messages\PoisonMessage;
use Siberfx\MessageBus\Tests\Fixtures\Messages\SendInvoice;
use Siberfx\MessageBus\Tests\Fixtures\Messages\Status;
use Siberfx\MessageBus\Tests\Fixtures\Messages\CreateUser;
use Siberfx\MessageBus\Tests\Fixtures\Recorder;
use Siberfx\MessageBus\Tests\TestCase;
use Siberfx\MessageBus\Transport\InMemory\InMemoryTransport;
use Siberfx\MessageBus\Transport\Queue\ConsumeEnvelopeJob;
use Siberfx\MessageBus\Transport\Serialization\JsonSerializer;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\BusNameStamp;
use Siberfx\MessageBus\Worker\Worker;

class TransportTest extends TestCase
{
    protected function consume(string ...$transports): int
    {
        return app(Worker::class)->run($transports ?: ['async'], ['stop_when_empty' => true, 'sleep' => 0]);
    }

    public function test_routed_messages_are_sent_and_handled_by_the_worker(): void
    {
        $envelope = MessageBus::dispatch(new SendInvoice(10));

        /** @var InMemoryTransport $async */
        $async = MessageBus::transport('async');

        $this->assertSame([], Recorder::$calls);
        $this->assertSame('async', $envelope->last(SentStamp::class)->senderAlias);
        $this->assertCount(1, $async->getSent());

        $this->assertSame(1, $this->consume());
        $this->assertSame(['invoice:10'], Recorder::$calls);
        $this->assertCount(1, $async->getAcknowledged());
        $this->assertSame(0, $async->getMessageCount());
    }

    public function test_attribute_routing_and_stamp_override(): void
    {
        MessageBus::dispatch(new AuditLog('login'));
        $this->assertSame([], Recorder::$calls);

        MessageBus::dispatch(new AuditLog('logout'), [new TransportNamesStamp('sync')]);
        $this->assertSame(['audit:logout'], Recorder::$calls);

        MessageBus::route(AuditLog::class, ['sync']);
        MessageBus::dispatch(new AuditLog('config'));
        $this->assertSame(['audit:logout', 'audit:config'], Recorder::$calls);
    }

    public function test_failed_messages_are_retried(): void
    {
        MessageBus::dispatch(new FlakyMessage('a', failTimes: 2));

        $this->consume();

        $this->assertSame(['flaky:a'], Recorder::$calls);
        $this->assertSame(3, Recorder::$attempts['a']);
        $this->assertSame(0, MessageBus::transport('failed')->getMessageCount());
    }

    public function test_messages_exceeding_retries_go_to_the_failure_transport(): void
    {
        MessageBus::dispatch(new FlakyMessage('b', failTimes: 99));

        $this->consume();

        $this->assertSame(4, Recorder::$attempts['b']);

        $failed = iterator_to_array(MessageBus::transport('failed')->all());
        $this->assertCount(1, $failed);
        $this->assertSame('async', $failed[0]->last(SentToFailureTransportStamp::class)->originalReceiverName);
        $this->assertSame(3, RedeliveryStamp::getRetryCountFromEnvelope($failed[0]));
        $this->assertSame('Temporary failure #4', $failed[0]->last(ErrorDetailsStamp::class)->exceptionMessage);
    }

    public function test_unrecoverable_exceptions_skip_retries(): void
    {
        MessageBus::dispatch(new PoisonMessage());

        $this->consume();

        $this->assertSame(1, Recorder::$attempts['poison']);
        $this->assertSame(1, MessageBus::transport('failed')->getMessageCount());
    }

    public function test_failed_messages_can_be_retried_with_the_command(): void
    {
        MessageBus::dispatch(new FlakyMessage('c', failTimes: 4));
        $this->consume();
        $this->assertSame(1, MessageBus::transport('failed')->getMessageCount());

        $this->artisan('message-bus:failed')->assertSuccessful();
        $this->artisan('message-bus:failed:retry', ['--all' => true])->assertSuccessful();

        $this->assertSame(['flaky:c'], Recorder::$calls);
        $this->assertSame(0, MessageBus::transport('failed')->getMessageCount());
    }

    public function test_database_transport(): void
    {
        config([
            'message-bus.routing' => [SendInvoice::class => 'database'],
            'message-bus.transports.failed' => ['driver' => 'database', 'queue' => 'failed'],
        ]);
        app(\Siberfx\MessageBus\MessageBusManager::class)->forgetBuses();
        $this->app->forgetInstance(\Siberfx\MessageBus\Transport\SendersLocator::class);

        MessageBus::dispatch(new SendInvoice(1));
        MessageBus::dispatch(new SendInvoice(2));

        $this->assertSame(2, MessageBus::transport('database')->getMessageCount());
        $this->assertDatabaseCount('message_bus_messages', 2);

        $this->artisan('message-bus:consume', ['transports' => ['database'], '--stop-when-empty' => true])->assertSuccessful();

        $this->assertSame(['invoice:1', 'invoice:2'], Recorder::$calls);
        $this->assertDatabaseCount('message_bus_messages', 0);
    }

    public function test_database_failure_transport_can_list_and_remove(): void
    {
        config(['message-bus.transports.failed' => ['driver' => 'database', 'queue' => 'failed']]);
        MessageBus::transports()->forget();

        MessageBus::dispatch(new PoisonMessage('broken'));
        $this->consume();

        $this->assertDatabaseHas('message_bus_messages', ['queue_name' => 'failed']);
        $id = MessageBus::transport('failed')->all()->current()->last(\Siberfx\MessageBus\Stamp\TransportMessageIdStamp::class)->id;

        $this->artisan('message-bus:failed', ['id' => $id])->expectsOutputToContain('Cannot process: broken')->assertSuccessful();
        $this->artisan('message-bus:failed:remove', ['id' => [$id], '--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('message_bus_messages', 0);
    }

    public function test_queue_transport_pushes_laravel_jobs(): void
    {
        config(['message-bus.transports.async' => ['driver' => 'queue', 'queue' => 'messages']]);
        MessageBus::transports()->forget();

        Queue::fake();

        MessageBus::dispatch(new SendInvoice(5));

        Queue::assertPushedOn('messages', ConsumeEnvelopeJob::class, fn (ConsumeEnvelopeJob $job) => $job->transportName === 'async'
            && $job->encodedEnvelope['headers']['type'] === SendInvoice::class);
        $this->assertSame([], Recorder::$calls);
    }

    public function test_queue_transport_handles_messages_through_the_queue(): void
    {
        config(['message-bus.transports.async' => ['driver' => 'queue']]);
        MessageBus::transports()->forget();

        MessageBus::dispatch(new SendInvoice(6));
        MessageBus::dispatch(new FlakyMessage('q', failTimes: 1));

        $this->assertSame(['invoice:6', 'flaky:q'], Recorder::$calls);
    }

    public function test_json_serializer_round_trip(): void
    {
        $serializer = app(JsonSerializer::class);
        $envelope = new Envelope(new CreateUser('Ada', 'ada@example.com', Status::Banned), [new BusNameStamp('command.bus'), new HandledStamp('x', 'h')]);

        $encoded = $serializer->encode($envelope);
        $this->assertSame(['name' => 'Ada', 'email' => 'ada@example.com', 'status' => 'banned'], json_decode($encoded['body'], true));
        $this->assertSame('user.create', $encoded['headers']['alias']);

        $decoded = $serializer->decode($encoded);
        $this->assertEquals($envelope->getMessage(), $decoded->getMessage());
        $this->assertSame('command.bus', $decoded->last(BusNameStamp::class)->busName);
    }

    public function test_in_memory_transport_can_serialize(): void
    {
        config(['message-bus.transports.async' => ['driver' => 'in-memory', 'serialize' => true, 'serializer' => 'json']]);
        MessageBus::transports()->forget();

        MessageBus::dispatch(new SendInvoice(9));
        $this->consume();

        $this->assertSame(['invoice:9'], Recorder::$calls);
    }
}
