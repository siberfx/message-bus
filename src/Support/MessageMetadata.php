<?php

namespace Siberfx\MessageBus\Support;

use ReflectionAttribute;
use ReflectionClass;
use Siberfx\MessageBus\Attribute\AsMessage;
use Siberfx\MessageBus\Attribute\BroadcastOn;

/**
 * Reads (and memoizes) the message level attributes of a class.
 */
final class MessageMetadata
{
    /**
     * @var array<string, array<string, object|null>>
     */
    private static array $cache = [];

    public static function asMessage(string $class): ?AsMessage
    {
        return self::attribute($class, AsMessage::class);
    }

    public static function broadcastOn(string $class): ?BroadcastOn
    {
        return self::attribute($class, BroadcastOn::class);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $attribute
     * @return T|null
     */
    private static function attribute(string $class, string $attribute): ?object
    {
        if (array_key_exists($attribute, self::$cache[$class] ?? [])) {
            return self::$cache[$class][$attribute];
        }

        $instance = null;

        if (class_exists($class)) {
            $reflection = new ReflectionClass($class);

            do {
                $attributes = $reflection->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF);

                if ($attributes !== []) {
                    $instance = $attributes[0]->newInstance();
                    break;
                }
            } while ($reflection = $reflection->getParentClass());
        }

        return self::$cache[$class][$attribute] = $instance;
    }
}
