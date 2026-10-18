<?php

namespace Siberfx\MessageBus\Console;

use Illuminate\Console\Command;
use Siberfx\MessageBus\Console\Concerns\InteractsWithFailureTransport;
use Siberfx\MessageBus\Exception\HandlerFailedException;
use Siberfx\MessageBus\MessageBusManager;
use Siberfx\MessageBus\Stamp\BusNameStamp;
use Siberfx\MessageBus\Stamp\DelayStamp;
use Siberfx\MessageBus\Stamp\ErrorDetailsStamp;
use Siberfx\MessageBus\Stamp\ReceivedStamp;
use Siberfx\MessageBus\Stamp\RedeliveryStamp;
use Siberfx\MessageBus\Stamp\SentToFailureTransportStamp;
use Siberfx\MessageBus\Transport\TransportManager;
use Throwable;

class FailedRetryCommand extends Command
{
    use InteractsWithFailureTransport;

    protected $signature = 'message-bus:failed:retry
        {id?* : IDs of the failed messages to retry}
        {--all : Retry every failed message}
        {--transport= : The failure transport (default: config failure_transport)}';

    protected $description = 'Handle failed messages again';

    public function handle(TransportManager $transports, MessageBusManager $manager): int
    {
        if (! $transport = $this->failureTransport($transports)) {
            return self::FAILURE;
        }

        if (! $this->option('all') && $this->argument('id') === []) {
            $this->components->error('Pass one or more message IDs, or --all.');

            return self::FAILURE;
        }

        $failures = 0;

        foreach ($this->selectedEnvelopes($transport, $this->argument('id'), (bool) $this->option('all')) as $envelope) {
            $original = $envelope->last(SentToFailureTransportStamp::class)?->originalReceiverName ?? $this->failureTransportName();
            $clean = $envelope
                ->withoutAll(RedeliveryStamp::class)
                ->withoutAll(ErrorDetailsStamp::class)
                ->withoutAll(DelayStamp::class);

            try {
                $manager->bus($envelope->last(BusNameStamp::class)?->busName)
                    ->dispatch($clean->with(new ReceivedStamp($original)));

                $transport->ack($envelope);

                $this->components->twoColumnDetail($envelope->getMessage()::class, '<fg=green;options=bold>DONE</>');
            } catch (Throwable $e) {
                $failures++;
                $transport->reject($envelope);

                $retry = $e instanceof HandlerFailedException ? $e->getEnvelope()->withoutAll(ReceivedStamp::class) : $clean;
                $transport->send($retry->withoutAll(ErrorDetailsStamp::class)->with(ErrorDetailsStamp::create($e)));

                $this->components->twoColumnDetail($envelope->getMessage()::class, '<fg=red;options=bold>FAIL</> '.$e->getMessage());
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
