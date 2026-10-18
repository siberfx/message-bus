<?php

namespace Siberfx\MessageBus\Console;

use Illuminate\Console\Command;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Transport\Queue\QueueTransport;
use Siberfx\MessageBus\Transport\TransportManager;
use Siberfx\MessageBus\Worker\Worker;

class ConsumeCommand extends Command
{
    protected $signature = 'message-bus:consume
        {transports?* : Transports to consume, in priority order (default: all pull based transports except the failure transport)}
        {--limit= : Stop after handling this many messages}
        {--time-limit= : Stop after this many seconds}
        {--memory-limit=128 : Stop when memory usage exceeds this many MB}
        {--sleep=1000 : Milliseconds to sleep when no message is available}
        {--stop-when-empty : Stop when all transports are empty}';

    protected $description = 'Consume messages from pull based transports (database, in-memory, custom)';

    public function handle(Worker $worker, TransportManager $transports): int
    {
        $names = $this->argument('transports') ?: $this->defaultTransports($transports);

        foreach ($names as $name) {
            if ($transports->get($name) instanceof QueueTransport) {
                $this->components->warn(sprintf('Transport [%s] uses the Laravel queue; consume it with `php artisan queue:work`.', $name));

                return self::FAILURE;
            }
        }

        if ($names === []) {
            $this->components->warn('No pull based transports to consume.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf('Consuming messages from [%s].', implode(', ', $names)));

        $this->listenForSignals($worker);

        $worker->onMessage(function (string $transport, Envelope $envelope, bool $success) {
            $this->components->twoColumnDetail(
                sprintf('<fg=gray>%s</> %s <fg=gray>(%s)</>', now()->format('H:i:s'), class_basename($envelope->getMessage()), $transport),
                $success ? '<fg=green;options=bold>DONE</>' : '<fg=red;options=bold>FAIL</>',
            );
        });

        $handled = $worker->run($names, [
            'limit' => $this->option('limit') !== null ? (int) $this->option('limit') : null,
            'time_limit' => $this->option('time-limit') !== null ? (int) $this->option('time-limit') : null,
            'memory_limit' => (int) $this->option('memory-limit'),
            'sleep' => (int) $this->option('sleep'),
            'stop_when_empty' => (bool) $this->option('stop-when-empty'),
        ]);

        $this->components->info(sprintf('Worker stopped (%s) after %d message(s).', $worker->stopReason(), $handled));

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function defaultTransports(TransportManager $transports): array
    {
        $failure = config('message-bus.failure_transport');

        return array_values(array_filter($transports->names(), function (string $name) use ($failure) {
            $driver = config("message-bus.transports.{$name}.driver");

            return $name !== $failure && ! in_array($driver, ['sync', 'queue'], true);
        }));
    }

    protected function listenForSignals(Worker $worker): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGINT, SIGTERM, SIGQUIT] as $signal) {
            pcntl_signal($signal, fn () => $worker->stop('signal received'));
        }
    }
}
