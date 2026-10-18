<?php

namespace Siberfx\MessageBus;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Siberfx\MessageBus\Console\CacheCommand;
use Siberfx\MessageBus\Console\ClearCommand;
use Siberfx\MessageBus\Console\ConsumeCommand;
use Siberfx\MessageBus\Console\DebugCommand;
use Siberfx\MessageBus\Console\FailedRemoveCommand;
use Siberfx\MessageBus\Console\FailedRetryCommand;
use Siberfx\MessageBus\Console\FailedShowCommand;
use Siberfx\MessageBus\Console\InstallCommand;
use Siberfx\MessageBus\Console\MakeMessageCommand;
use Siberfx\MessageBus\Console\MakeMessageHandlerCommand;
use Siberfx\MessageBus\Console\StatsCommand;
use Siberfx\MessageBus\Console\StopWorkersCommand;
use Siberfx\MessageBus\Contracts\HandlersLocatorInterface;
use Siberfx\MessageBus\Contracts\MessageBusInterface;
use Siberfx\MessageBus\Contracts\SerializerInterface;
use Siberfx\MessageBus\Discovery\DiscoveryCache;
use Siberfx\MessageBus\Handler\HandlerDescriptor;
use Siberfx\MessageBus\Handler\HandlersLocator;
use Siberfx\MessageBus\Realtime\BroadcastResolver;
use Siberfx\MessageBus\Realtime\RealtimeManager;
use Siberfx\MessageBus\Support\MessageHydrator;
use Siberfx\MessageBus\Support\MessageRegistry;
use Siberfx\MessageBus\Transport\SendersLocator;
use Siberfx\MessageBus\Transport\TransportManager;
use Siberfx\MessageBus\View\ScriptTags;
use Siberfx\MessageBus\Worker\EnvelopeProcessor;
use Siberfx\MessageBus\Worker\Worker;

class MessageBusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/message-bus.php', 'message-bus');

        $this->app->singleton(DiscoveryCache::class);
        $this->app->singleton(MessageHydrator::class);
        $this->app->singleton(TransportManager::class);
        $this->app->singleton(RealtimeManager::class);
        $this->app->singleton(MessageBusManager::class);
        $this->app->alias(MessageBusManager::class, 'message-bus');

        $this->app->singleton(MessageRegistry::class, function (Application $app) {
            $registry = new MessageRegistry();

            foreach ($app->make(DiscoveryCache::class)->get()['messages'] as $class => $message) {
                $registry->register($class, $message['alias'] ?? null, (bool) ($message['exposed'] ?? false));
            }

            foreach ((array) $app['config']->get('message-bus.http.exposed', []) as $alias => $class) {
                $registry->expose($alias, $class);
            }

            return $registry;
        });

        $this->app->singleton(HandlersLocator::class, function (Application $app) {
            $locator = new HandlersLocator();

            foreach ($app->make(DiscoveryCache::class)->get()['handlers'] as $handler) {
                $locator->add($handler['message'], new HandlerDescriptor($handler['handler'], $handler['options'] ?? []));
            }

            return $locator->addFromConfig((array) $app['config']->get('message-bus.handlers', []));
        });
        $this->app->alias(HandlersLocator::class, HandlersLocatorInterface::class);

        $this->app->singleton(SendersLocator::class, fn (Application $app) => new SendersLocator(
            $app->make(TransportManager::class),
            (array) $app['config']->get('message-bus.routing', []),
        ));

        $this->app->singleton(BroadcastResolver::class);

        $this->app->bind(SerializerInterface::class, fn (Application $app) => $app->make(TransportManager::class)->makeSerializer());

        $this->app->bind(MessageBusInterface::class, fn (Application $app) => $app->make(MessageBusManager::class)->bus());

        $this->app->bind(EnvelopeProcessor::class, fn (Application $app) => new EnvelopeProcessor(
            $app->make(MessageBusManager::class),
            $app->make(TransportManager::class),
            $app->make('events'),
            $app->make('log'),
        ));

        $this->app->bind(Worker::class, fn (Application $app) => new Worker(
            $app->make(TransportManager::class),
            $app->make(EnvelopeProcessor::class),
            $app->make('events'),
            $app->bound('cache') ? $app->make('cache')->store() : null,
        ));
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/message-bus.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'message-bus');

        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'message-bus');
        Blade::directive('messageBusScripts', fn (string $expression) => '<?php echo app(\\'.ScriptTags::class.'::class)->render('.($expression ?: '[]').'); ?>');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/message-bus.php' => config_path('message-bus.php'),
        ], 'message-bus-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'message-bus-migrations');

        $this->publishes([
            __DIR__.'/../stubs' => base_path('stubs/message-bus'),
        ], 'message-bus-stubs');

        $this->publishes([
            __DIR__.'/../resources/js' => resource_path('js/vendor/message-bus'),
        ], 'message-bus-js');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/message-bus'),
        ], 'message-bus-views');

        $this->commands([
            InstallCommand::class,
            ConsumeCommand::class,
            StopWorkersCommand::class,
            FailedShowCommand::class,
            FailedRetryCommand::class,
            FailedRemoveCommand::class,
            DebugCommand::class,
            StatsCommand::class,
            CacheCommand::class,
            ClearCommand::class,
            MakeMessageCommand::class,
            MakeMessageHandlerCommand::class,
        ]);
    }
}
