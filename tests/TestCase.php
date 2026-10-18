<?php

namespace Siberfx\MessageBus\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Siberfx\MessageBus\MessageBusServiceProvider;
use Siberfx\MessageBus\Support\MessageMetadata;
use Siberfx\MessageBus\Tests\Fixtures\Messages\SendInvoice;
use Siberfx\MessageBus\Tests\Fixtures\Recorder;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Recorder::reset();
        MessageMetadata::flush();
    }

    protected function getPackageProviders($app): array
    {
        return [MessageBusServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['MessageBus' => \Siberfx\MessageBus\Facades\MessageBus::class];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        $config->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $config->set('database.default', 'testing');
        $config->set('queue.default', 'sync');
        $config->set('message-bus.discovery.paths', [__DIR__.'/Fixtures']);
        $config->set('message-bus.discovery.cache', null);
        $config->set('message-bus.transports.async', ['driver' => 'in-memory']);
        $config->set('message-bus.transports.failed', ['driver' => 'in-memory']);
        $config->set('message-bus.retry', ['max_retries' => 3, 'delay' => 0, 'multiplier' => 1, 'max_delay' => 0]);
        $config->set('message-bus.realtime.default', 'null');
        $config->set('message-bus.routing', [SendInvoice::class => 'async']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
