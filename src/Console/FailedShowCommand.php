<?php

namespace Siberfx\MessageBus\Console;

use Illuminate\Console\Command;
use Siberfx\MessageBus\Console\Concerns\InteractsWithFailureTransport;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Stamp\ErrorDetailsStamp;
use Siberfx\MessageBus\Stamp\RedeliveryStamp;
use Siberfx\MessageBus\Stamp\SentToFailureTransportStamp;
use Siberfx\MessageBus\Stamp\TransportMessageIdStamp;
use Siberfx\MessageBus\Transport\TransportManager;

class FailedShowCommand extends Command
{
    use InteractsWithFailureTransport;

    protected $signature = 'message-bus:failed
        {id? : Show the details of a single failed message}
        {--max=50 : Maximum number of messages to list}
        {--transport= : The failure transport to read (default: config failure_transport)}';

    protected $description = 'List failed messages, or show one in detail';

    public function handle(TransportManager $transports): int
    {
        if (! $transport = $this->failureTransport($transports)) {
            return self::FAILURE;
        }

        if ($id = $this->argument('id')) {
            $envelope = $transport->find($id);

            if ($envelope === null) {
                $this->components->error(sprintf('Failed message [%s] not found.', $id));

                return self::FAILURE;
            }

            $this->showDetails($envelope);

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($transport->all((int) $this->option('max')) as $envelope) {
            $error = $envelope->last(ErrorDetailsStamp::class);

            $rows[] = [
                $envelope->last(TransportMessageIdStamp::class)?->id,
                $envelope->getMessage()::class,
                $envelope->last(SentToFailureTransportStamp::class)?->originalReceiverName,
                RedeliveryStamp::getRetryCountFromEnvelope($envelope),
                str($error?->exceptionMessage)->limit(60),
            ];
        }

        if ($rows === []) {
            $this->components->info('No failed messages.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Message', 'Original transport', 'Retries', 'Error'], $rows);

        return self::SUCCESS;
    }

    protected function showDetails(Envelope $envelope): void
    {
        $error = $envelope->last(ErrorDetailsStamp::class);

        $this->components->twoColumnDetail('Message', $envelope->getMessage()::class);
        $this->components->twoColumnDetail('Original transport', $envelope->last(SentToFailureTransportStamp::class)?->originalReceiverName ?? '-');
        $this->components->twoColumnDetail('Retries', (string) RedeliveryStamp::getRetryCountFromEnvelope($envelope));
        $this->components->twoColumnDetail('Exception', $error?->exceptionClass ?? '-');
        $this->components->twoColumnDetail('Error', $error?->exceptionMessage ?? '-');

        $this->newLine();
        $this->line('<options=bold>Payload</>');
        $this->line(json_encode(get_object_vars($envelope->getMessage()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');

        if ($error?->trace && $this->output->isVerbose()) {
            $this->newLine();
            $this->line('<options=bold>Trace</>');
            $this->line($error->trace);
        }
    }
}
