<?php

namespace Siberfx\MessageBus\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'message-bus:install {--migrate : Run the migrations after publishing}';

    protected $description = 'Publish the Message Bus config and migration';

    public function handle(): int
    {
        $this->callSilently('vendor:publish', ['--tag' => 'message-bus-config']);
        $this->components->task('Publishing config/message-bus.php');

        $this->callSilently('vendor:publish', ['--tag' => 'message-bus-migrations']);
        $this->components->task('Publishing the message_bus_messages migration');

        if ($this->option('migrate') || $this->confirm('Run the migrations now?', true)) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->components->info('Message Bus installed. Next steps:');
        $this->components->bulletList([
            'Create a message:            <fg=yellow>php artisan make:message PlaceOrder --handler</>',
            'Realtime (Reverb):           <fg=yellow>php artisan install:broadcasting</>',
            'Blade / Alpine:              add <fg=yellow>@messageBusScripts</> before </body>',
            'React / Vue:                 import from <fg=yellow>vendor/siberfx/message-bus/resources/js/react.js|vue.js</>',
            'Consume database transport:  <fg=yellow>php artisan message-bus:consume database</>',
            'Consume queue transport:     <fg=yellow>php artisan queue:work --queue=messages</>',
        ]);

        return self::SUCCESS;
    }
}
