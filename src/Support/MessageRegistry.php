<?php

namespace Siberfx\MessageBus\Support;

/**
 * Keeps track of message aliases and which messages may be dispatched by
 * the frontend over HTTP.
 */
class MessageRegistry
{
    /**
     * @var array<string, class-string>
     */
    protected array $aliases = [];

    /**
     * @var array<string, true>
     */
    protected array $exposed = [];

    public function register(string $class, ?string $alias = null, bool $exposed = false): static
    {
        $alias ??= MessageMetadata::asMessage($class)?->alias;

        if ($alias === null) {
            return $this;
        }

        $this->aliases[$alias] = $class;

        if ($exposed) {
            $this->exposed[$alias] = true;
        }

        return $this;
    }

    /**
     * Allow the frontend to dispatch the given message class using the alias.
     */
    public function expose(string $alias, string $class): static
    {
        return $this->register($class, $alias, true);
    }

    public function classFor(string $alias): ?string
    {
        return $this->aliases[$alias] ?? null;
    }

    public function exposedClass(string $alias): ?string
    {
        return isset($this->exposed[$alias]) ? $this->classFor($alias) : null;
    }

    public function isExposed(string $alias): bool
    {
        return isset($this->exposed[$alias]);
    }

    public function aliasFor(string $class): ?string
    {
        $alias = array_search($class, $this->aliases, true);

        return $alias !== false ? $alias : MessageMetadata::asMessage($class)?->alias;
    }

    /**
     * @return array<string, array{class: class-string, exposed: bool}>
     */
    public function all(): array
    {
        $all = [];

        foreach ($this->aliases as $alias => $class) {
            $all[$alias] = ['class' => $class, 'exposed' => isset($this->exposed[$alias])];
        }

        ksort($all);

        return $all;
    }
}
