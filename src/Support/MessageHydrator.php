<?php

namespace Siberfx\MessageBus\Support;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use Siberfx\MessageBus\Exception\HydrationException;
use Throwable;

/**
 * Builds message objects from plain arrays (HTTP payloads, JSON transports).
 *
 * Constructor parameters are matched by name (camelCase or snake_case keys),
 * scalars are cast, backed enums / dates / nested objects are built and
 * Eloquent models are resolved from their key.
 */
class MessageHydrator
{
    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $data
     * @return T
     */
    public function hydrate(string $class, array $data): object
    {
        if (! class_exists($class)) {
            throw new HydrationException(sprintf('Message class "%s" does not exist.', $class));
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            $instance = $reflection->newInstance();

            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic() || $property->isReadOnly()) {
                    continue;
                }

                $key = $this->keyFor($property->getName(), $data);

                if ($key !== null) {
                    $property->setValue($instance, $this->cast($data[$key], $property->getType(), $property->getName()));
                }
            }

            return $instance;
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                continue;
            }

            $arguments[$parameter->getName()] = $this->valueFor($parameter, $data);
        }

        return $reflection->newInstanceArgs($arguments);
    }

    protected function valueFor(ReflectionParameter $parameter, array $data): mixed
    {
        $name = $parameter->getName();
        $key = $this->keyFor($name, $data);

        if ($key === null) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            if ($parameter->allowsNull()) {
                return null;
            }

            throw new HydrationException(sprintf('The %s field is required.', Str::snake($name, ' ')), $name);
        }

        return $this->cast($data[$key], $parameter->getType(), $name);
    }

    protected function keyFor(string $name, array $data): ?string
    {
        foreach ([$name, Str::snake($name)] as $candidate) {
            if (array_key_exists($candidate, $data)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function cast(mixed $value, ?ReflectionType $type, string $field): mixed
    {
        if ($value === null || ! $type instanceof ReflectionNamedType || $type->getName() === 'mixed') {
            return $value;
        }

        $name = $type->getName();

        if ($type->isBuiltin()) {
            return $this->castBuiltin($value, $name, $field);
        }

        if ($value instanceof $name) {
            return $value;
        }

        try {
            return match (true) {
                is_subclass_of($name, BackedEnum::class) => $name::tryFrom($value)
                    ?? throw new HydrationException(sprintf('The selected %s is invalid.', Str::snake($field, ' ')), $field),
                is_a($name, DateTimeInterface::class, true) => $this->date($name, $value),
                is_subclass_of($name, Model::class) && is_scalar($value) => $name::query()->findOrFail($value),
                is_array($value) && class_exists($name) => $this->hydrate($name, $value),
                default => $value,
            };
        } catch (HydrationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new HydrationException(sprintf('The %s field is invalid.', Str::snake($field, ' ')), $field);
        }
    }

    protected function castBuiltin(mixed $value, string $type, string $field): mixed
    {
        $invalid = fn () => throw new HydrationException(
            sprintf('The %s field must be of type %s.', Str::snake($field, ' '), $type),
            $field,
        );

        return match ($type) {
            'int' => is_numeric($value) && (int) $value == $value ? (int) $value : $invalid(),
            'float' => is_numeric($value) ? (float) $value : $invalid(),
            'bool' => is_bool($value) ? $value : (filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $invalid()),
            'string' => is_scalar($value) ? (string) $value : $invalid(),
            'array', 'iterable' => is_array($value) ? $value : $invalid(),
            default => $value,
        };
    }

    protected function date(string $class, mixed $value): DateTimeInterface
    {
        if ($class === DateTimeInterface::class || interface_exists($class)) {
            $class = CarbonImmutable::class;
        }

        return is_numeric($value) ? (new $class())->setTimestamp((int) $value) : new $class($value);
    }
}
