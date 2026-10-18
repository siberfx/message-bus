<?php

namespace Siberfx\MessageBus\Tests\Fixtures;

final class Recorder
{
    /** @var list<string> */
    public static array $calls = [];

    /** @var array<string, int> */
    public static array $attempts = [];

    public static function record(string $call): void
    {
        self::$calls[] = $call;
    }

    public static function attempt(string $key): int
    {
        return self::$attempts[$key] = (self::$attempts[$key] ?? 0) + 1;
    }

    public static function reset(): void
    {
        self::$calls = [];
        self::$attempts = [];
    }
}
