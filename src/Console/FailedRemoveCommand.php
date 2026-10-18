<?php

namespace Siberfx\MessageBus\Console;

use Illuminate\Console\Command;
use Siberfx\MessageBus\Console\Concerns\InteractsWithFailureTransport;
use Siberfx\MessageBus\Transport\TransportManager;

class FailedRemoveCommand extends Command
{
    use InteractsWithFailureTransport;

    protected $signature = 'message-bus:failed:remove
        {id?* : IDs of the failed messages to remove}
        {--all : Remove every failed message}
        {--force : Do not ask for confirmation}
        {--transport= : The failure transport (default: config failure_transport)}';

    protected $description = 'Remove failed messages from the failure transport';

    public function handle(TransportManager $transports): int
    {
        if (! $transport = $this->failureTransport($transports)) {
            return self::FAILURE;
        }

        if (! $this->option('all') && $this->argument('id') === []) {
            $this->components->error('Pass one or more message IDs, or --all.');

            return self::FAILURE;
        }

        $envelopes = $this->selectedEnvelopes($transport, $this->argument('id'), (bool) $this->option('all'));

        if ($envelopes === []) {
            $this->components->info('Nothing to remove.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(sprintf('Remove %d failed message(s)?', count($envelopes)))) {
            return self::SUCCESS;
        }

        foreach ($envelopes as $envelope) {
            $transport->reject($envelope);
        }

        $this->components->info(sprintf('Removed %d failed message(s).', count($envelopes)));

        return self::SUCCESS;
    }
}
