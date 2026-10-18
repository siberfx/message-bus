<?php

namespace Siberfx\MessageBus\Support;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Stringable;
use UnitEnum;

/**
 * Turns messages and handler results into JSON friendly arrays.
 */
final class Normalizer
{
    public static function normalize(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_scalar($value) => $value,
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof JsonSerializable => self::normalize($value->jsonSerialize()),
            $value instanceof Arrayable => self::normalize($value->toArray()),
            is_array($value) => array_map(self::normalize(...), $value),
            is_object($value) => self::normalizeObject($value),
            default => null,
        };
    }

    /**
     * Normalize a message into an associative array of its public data.
     *
     * @return array<string, mixed>
     */
    public static function toArray(object $message): array
    {
        $normalized = self::normalize($message);

        return is_array($normalized) ? $normalized : ['value' => $normalized];
    }

    /**
     * The public, initialized properties of an object.
     *
     * @return array<string, mixed>
     */
    public static function publicProperties(object $object): array
    {
        return get_object_vars($object);
    }

    private static function normalizeObject(object $value): mixed
    {
        $properties = self::publicProperties($value);

        if ($properties === [] && $value instanceof Stringable) {
            return (string) $value;
        }

        return array_map(self::normalize(...), $properties);
    }
}
