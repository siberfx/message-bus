<?php

namespace Siberfx\MessageBus\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class MakeMessageHandlerCommand extends GeneratorCommand
{
    protected $name = 'make:message-handler';

    protected $description = 'Create a new message handler class';

    protected $type = 'Message handler';

    protected function getStub(): string
    {
        return file_exists($custom = $this->laravel->basePath('stubs/message-bus/handler.stub'))
            ? $custom
            : dirname(__DIR__, 2).'/stubs/handler.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\MessageHandlers';
    }

    protected function buildClass($name): string
    {
        $message = $this->option('message')
            ?: $this->rootNamespace().'Messages\\'.Str::beforeLast(class_basename($name), 'Handler');

        $message = ltrim(str_replace('/', '\\', $message), '\\');

        if (! str_contains($message, '\\')) {
            $message = $this->rootNamespace().'Messages\\'.$message;
        }

        return str_replace(
            ['{{ messageNamespace }}', '{{ message }}'],
            [$message, class_basename($message)],
            parent::buildClass($name),
        );
    }

    protected function getOptions(): array
    {
        return [
            ['message', 'm', InputOption::VALUE_REQUIRED, 'The message class handled by this handler'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if it already exists'],
        ];
    }
}
