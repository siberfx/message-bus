<?php

namespace Siberfx\MessageBus\Console;

use Illuminate\Console\Command;
use Siberfx\MessageBus\Discovery\DiscoveryCache;

class CacheCommand extends Command
{
    protected $signature = 'message-bus:cache';

    protected $description = 'Cache discovered message handlers and messages';

    public function handle(DiscoveryCache $cache): int
    {
        $this->components->info(sprintf('Message bus discovery cached to [%s].', $cache->cache()));

        return self::SUCCESS;
    }
}
