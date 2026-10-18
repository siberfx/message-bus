<?php

namespace Siberfx\MessageBus\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class MakeMessageCommand extends GeneratorCommand
{
    protected $name = 'make:message';

    protected $description = 'Create a new message class (command, query or event)';

    protected $type = 'Message';

    public function handle(): ?bool
    {
        if (parent::handle() === false) {
            return false;
        }

        if ($this->option('handler')) {
            $this->call('make:message-handler', [
                'name' => class_basename($this->argument('name')).'Handler',
                '--message' => $this->qualifyClass($this->getNameInput()),
            ]);
        }

        return null;
    }

    protected function getStub(): string
    {
        $stub = $this->option('broadcast') ? 'message.broadcast.stub' : 'message.stub';

        return file_exists($custom = $this->laravel->basePath('stubs/message-bus/'.$stub))
            ? $custom
            : dirname(__DIR__, 2).'/stubs/'.$stub;
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Messages';
    }

    protected function buildClass($name): string
    {
        $alias = Str::snake(class_basename($name), '.');
        $arguments = array_filter([
            $this->option('async') ? "transport: 'async'" : null,
            "alias: '{$alias}'",
            $this->option('exposed') ? 'exposed: true' : null,
        ]);

        return str_replace(
            ['{{ attributeArguments }}', '{{ alias }}'],
            [implode(', ', $arguments), $alias],
            parent::buildClass($name),
        );
    }

    protected function getOptions(): array
    {
        return [
            ['handler', null, InputOption::VALUE_NONE, 'Also create a handler for the message'],
            ['async', 'a', InputOption::VALUE_NONE, 'Route the message to the "async" transport'],
            ['exposed', 'e', InputOption::VALUE_NONE, 'Allow the frontend to dispatch the message over HTTP'],
            ['broadcast', 'b', InputOption::VALUE_NONE, 'Broadcast the message to realtime channels once handled'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if it already exists'],
        ];
    }
}
