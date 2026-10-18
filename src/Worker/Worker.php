<?php

namespace Siberfx\MessageBus\Worker;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Events\Dispatcher;
use Siberfx\MessageBus\Envelope;
use Siberfx\MessageBus\Events\WorkerStarted;
use Siberfx\MessageBus\Events\WorkerStopped;
use Siberfx\MessageBus\Transport\TransportManager;

/**
 * Long running consumer for pull based transports (database, in-memory or
 * your own). Transports are polled in the given order, so the first one
 * has the highest priority.
 */
class Worker
{
    public const RESTART_CACHE_KEY = 'message-bus:restart';

    protected bool $shouldStop = false;

    protected string $stopReason = 'stopped';

    protected int $handled = 0;

    /**
     * @var (callable(string, Envelope, bool): void)|null
     */
    protected $onMessage = null;

    public function __construct(
        protected TransportManager $transports,
        protected EnvelopeProcessor $processor,
        protected ?Dispatcher $events = null,
        protected ?Cache $cache = null,
    ) {
    }

    /**
     * @param  list<string>  $transportNames
     * @param  array{limit?: int|null, time_limit?: int|null, memory_limit?: int|null, sleep?: int, stop_when_empty?: bool}  $options
     */
    public function run(array $transportNames, array $options = []): int
    {
        $options += ['limit' => null, 'time_limit' => null, 'memory_limit' => null, 'sleep' => 1000, 'stop_when_empty' => false];

        $this->shouldStop = false;
        $this->handled = 0;
        $startedAt = time();
        $lastRestart = $this->cache?->get(self::RESTART_CACHE_KEY);

        $this->events?->dispatch(new WorkerStarted($transportNames));

        while (! $this->shouldStop) {
            $received = false;

            foreach ($transportNames as $name) {
                $transport = $this->transports->get($name);

                foreach ($transport->get() as $envelope) {
                    $received = true;
                    $success = $this->processor->process($name, $envelope, $transport);
                    $this->handled++;

                    if ($this->onMessage) {
                        ($this->onMessage)($name, $envelope, $success);
                    }

                    if ($this->limitsReached($options, $startedAt, $lastRestart)) {
                        break 3;
                    }
                }

                // Start again from the first (highest priority) transport.
                if ($received) {
                    break;
                }
            }

            if (! $received) {
                if ($options['stop_when_empty']) {
                    $this->stop('queue empty');
                    break;
                }

                usleep(max(0, (int) $options['sleep']) * 1000);
            }

            $this->limitsReached($options, $startedAt, $lastRestart);
        }

        $this->events?->dispatch(new WorkerStopped($transportNames, $this->handled, $this->stopReason));

        return $this->handled;
    }

    /**
     * @param  callable(string, Envelope, bool): void  $callback
     */
    public function onMessage(callable $callback): static
    {
        $this->onMessage = $callback;

        return $this;
    }

    public function stop(string $reason = 'stopped'): void
    {
        $this->shouldStop = true;
        $this->stopReason = $reason;
    }

    public function stopReason(): string
    {
        return $this->stopReason;
    }

    protected function limitsReached(array $options, int $startedAt, mixed $lastRestart): bool
    {
        match (true) {
            $options['limit'] !== null && $this->handled >= $options['limit'] => $this->stop('message limit reached'),
            $options['time_limit'] !== null && time() - $startedAt >= $options['time_limit'] => $this->stop('time limit reached'),
            $options['memory_limit'] !== null && memory_get_usage(true) >= $options['memory_limit'] * 1024 * 1024 => $this->stop('memory limit reached'),
            $this->cache !== null && $this->cache->get(self::RESTART_CACHE_KEY) != $lastRestart => $this->stop('restart signal received'),
            default => null,
        };

        return $this->shouldStop;
    }
}
