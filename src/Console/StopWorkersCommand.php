<?php

namespace Siberfx\MessageBus\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;
use Siberfx\MessageBus\Worker\Worker;

class StopWorkersCommand extends Command
{
    protected $signature = 'message-bus:stop-workers';

    protected $description = 'Gracefully stop all message-bus:consume workers after their current message';

    public function handle(Cache $cache): int
    {
        $cache->forever(Worker::RESTART_CACHE_KEY, microtime(true));

        $this->components->info('Broadcasting stop signal to workers.');

        return self::SUCCESS;
    }
}
