<?php

namespace Siberfx\MessageBus\Tests\Fixtures\Handlers;

use RuntimeException;
use Siberfx\MessageBus\Attribute\AsMessageHandler;
use Siberfx\MessageBus\Exception\UnrecoverableMessageHandlingException;
use Siberfx\MessageBus\Tests\Fixtures\Messages\AuditLog;
use Siberfx\MessageBus\Tests\Fixtures\Messages\ChatMessageSent;
use Siberfx\MessageBus\Tests\Fixtures\Messages\CounterIncremented;
use Siberfx\MessageBus\Tests\Fixtures\Messages\FlakyMessage;
use Siberfx\MessageBus\Tests\Fixtures\Messages\PoisonMessage;
use Siberfx\MessageBus\Tests\Fixtures\Messages\SecretMessage;
use Siberfx\MessageBus\Tests\Fixtures\Messages\SendInvoice;
use Siberfx\MessageBus\Tests\Fixtures\Recorder;

final class MiscHandlers
{
    #[AsMessageHandler]
    public function invoice(SendInvoice $message): string
    {
        Recorder::record('invoice:'.$message->invoiceId);

        return 'sent';
    }

    #[AsMessageHandler]
    public function audit(AuditLog $message): void
    {
        Recorder::record('audit:'.$message->action);
    }

    #[AsMessageHandler]
    public function chat(ChatMessageSent $message): array
    {
        Recorder::record('chat:'.$message->body);

        return ['stored' => true];
    }

    #[AsMessageHandler]
    public function counter(CounterIncremented $message): void
    {
        Recorder::record('counter:'.$message->value);
    }

    #[AsMessageHandler]
    public function flaky(FlakyMessage $message): void
    {
        if (Recorder::attempt($message->key) <= $message->failTimes) {
            throw new RuntimeException('Temporary failure #'.Recorder::$attempts[$message->key]);
        }

        Recorder::record('flaky:'.$message->key);
    }

    #[AsMessageHandler]
    public function poison(PoisonMessage $message): void
    {
        Recorder::attempt('poison');

        throw new UnrecoverableMessageHandlingException('Cannot process: '.$message->reason);
    }

    #[AsMessageHandler]
    public function secret(SecretMessage $message): string
    {
        return 'revealed';
    }
}
