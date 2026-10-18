<?php

namespace Siberfx\MessageBus\Console;

use Illuminate\Console\Command;
use Siberfx\MessageBus\Contracts\MessageCountAwareInterface;
use Siberfx\MessageBus\Transport\TransportManager;
use Throwable;

class StatsCommand extends Command
{
    protected $signature = 'message-bus:stats {transports?*}';

    protected $description = 'Show the number of pending messages per transport';

    public function handle(TransportManager $transports): int
    {
        $rows = [];

        foreach ($this->argument('transports') ?: $transports->names() as $name) {
            try {
                $transport = $transports->get($name);
                $count = $transport instanceof MessageCountAwareInterface ? $transport->getMessageCount() : 'n/a';
            } catch (Throwable $e) {
                $count = '<fg=red>'.$e->getMessage().'</>';
            }

            $rows[] = [$name, config("message-bus.transports.{$name}.driver", 'custom'), $count];
        }

        $this->table(['Transport', 'Driver', 'Pending'], $rows);

        return self::SUCCESS;
    }
}
