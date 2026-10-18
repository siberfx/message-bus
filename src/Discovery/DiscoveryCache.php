<?php

namespace Siberfx\MessageBus\Discovery;

use Illuminate\Contracts\Config\Repository;

/**
 * Runs attribute discovery once per process, or reads the cached result
 * written by `php artisan message-bus:cache`.
 */
class DiscoveryCache
{
    protected ?array $discovered = null;

    public function __construct(protected Repository $config)
    {
    }

    /**
     * @return array{handlers: list<array<string, mixed>>, messages: array<string, array<string, mixed>>}
     */
    public function get(): array
    {
        if ($this->discovered !== null) {
            return $this->discovered;
        }

        if (! $this->config->get('message-bus.discovery.enabled', true)) {
            return $this->discovered = ['handlers' => [], 'messages' => []];
        }

        $path = $this->path();

        if ($path !== null && is_file($path)) {
            return $this->discovered = require $path;
        }

        return $this->discovered = $this->discover();
    }

    /**
     * @return array{handlers: list<array<string, mixed>>, messages: array<string, array<string, mixed>>}
     */
    public function discover(): array
    {
        return (new AttributeDiscovery((array) $this->config->get('message-bus.discovery.paths', [])))->discover();
    }

    public function cache(): string
    {
        $path = $this->path() ?? throw new \RuntimeException('No discovery cache path configured.');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, '<?php return '.var_export($this->discover(), true).';'.PHP_EOL);

        $this->discovered = null;

        return $path;
    }

    public function clear(): bool
    {
        $this->discovered = null;
        $path = $this->path();

        return $path !== null && is_file($path) && unlink($path);
    }

    public function path(): ?string
    {
        return $this->config->get('message-bus.discovery.cache');
    }
}
