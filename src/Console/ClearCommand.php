<?php

namespace Siberfx\MessageBus\Console;

use Illuminate\Console\Command;
use Siberfx\MessageBus\Discovery\DiscoveryCache;

class ClearCommand extends Command
{
    protected $signature = 'message-bus:clear';

    protected $description = 'Remove the message bus discovery cache';

    public function handle(DiscoveryCache $cache): int
    {
        $cache->clear();

        $this->components->info('Message bus discovery cache cleared.');

        return self::SUCCESS;
    }
}
