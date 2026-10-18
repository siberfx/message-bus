<?php

namespace Siberfx\MessageBus\Console;

use Illuminate\Console\Command;
use Siberfx\MessageBus\Handler\HandlerDescriptor;
use Siberfx\MessageBus\MessageBusManager;

class DebugCommand extends Command
{
    protected $signature = 'message-bus:debug';

    protected $description = 'List buses, handlers, routing and exposed messages';

    public function handle(MessageBusManager $manager): int
    {
        $this->components->info('Buses');
        foreach ($manager->busNames() as $bus) {
            $instance = $manager->realBus($bus);
            $middleware = method_exists($instance, 'middleware')
                ? array_map(fn (object $middleware) => class_basename($middleware), $instance->middleware())
                : [];
            $this->components->twoColumnDetail(
                $bus.($bus === $manager->defaultBus() ? ' <fg=gray>(default)</>' : ''),
                implode(' → ', $middleware),
            );
        }

        $this->newLine();
        $this->components->info('Handlers');
        $rows = [];
        foreach ($manager->handlers()->all() as $message => $descriptors) {
            foreach ($descriptors as $descriptor) {
                /** @var HandlerDescriptor $descriptor */
                $rows[] = [$message, $descriptor->name(), $descriptor->bus() ?? '*', $descriptor->fromTransport() ?? '*', $descriptor->priority()];
            }
        }
        $rows === []
            ? $this->line('  No handlers registered.')
            : $this->table(['Message', 'Handler', 'Bus', 'From transport', 'Priority'], $rows);

        $this->newLine();
        $this->components->info('Routing');
        $routing = $manager->senders()->routing();
        if ($routing === []) {
            $this->line('  No config routing (unrouted messages are handled synchronously).');
        }
        foreach ($routing as $message => $transports) {
            $this->components->twoColumnDetail($message, implode(', ', (array) $transports));
        }

        $this->newLine();
        $this->components->info('Aliases');
        $aliases = $manager->registry()->all();
        if ($aliases === []) {
            $this->line('  No aliases registered.');
        }
        foreach ($aliases as $alias => $info) {
            $this->components->twoColumnDetail($alias.($info['exposed'] ? ' <fg=green>(exposed)</>' : ''), $info['class']);
        }

        return self::SUCCESS;
    }
}
