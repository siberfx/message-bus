<?php

namespace Siberfx\MessageBus\Discovery;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Siberfx\MessageBus\Attribute\AsMessage;
use Siberfx\MessageBus\Attribute\AsMessageHandler;
use Siberfx\MessageBus\Exception\LogicException;
use SplFileInfo;

/**
 * Scans directories for #[AsMessageHandler] and #[AsMessage] attributes.
 */
class AttributeDiscovery
{
    /**
     * @param  list<string>  $paths
     */
    public function __construct(protected array $paths)
    {
    }

    /**
     * @return array{
     *     handlers: list<array{message: string, handler: class-string, options: array<string, mixed>}>,
     *     messages: array<class-string, array{alias: string|null, exposed: bool, transport: string|list<string>|null, bus: string|null}>
     * }
     */
    public function discover(): array
    {
        $handlers = [];
        $messages = [];

        foreach ($this->classes() as $class) {
            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                continue;
            }

            foreach ($reflection->getAttributes(AsMessage::class) as $attribute) {
                $asMessage = $attribute->newInstance();

                $messages[$class] = [
                    'alias' => $asMessage->alias,
                    'exposed' => $asMessage->exposed,
                    'transport' => $asMessage->transport,
                    'bus' => $asMessage->bus,
                ];
            }

            foreach ($reflection->getAttributes(AsMessageHandler::class) as $attribute) {
                $asHandler = $attribute->newInstance();
                $method = $asHandler->method ?? '__invoke';

                if (! $reflection->hasMethod($method)) {
                    throw new LogicException(sprintf('Message handler "%s" has no "%s" method.', $class, $method));
                }

                array_push($handlers, ...$this->entries($class, $reflection->getMethod($method), $asHandler));
            }

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(AsMessageHandler::class) as $attribute) {
                    array_push($handlers, ...$this->entries($class, $method, $attribute->newInstance()));
                }
            }
        }

        return ['handlers' => $handlers, 'messages' => $messages];
    }

    /**
     * @return list<array{message: string, handler: class-string, options: array<string, mixed>}>
     */
    protected function entries(string $class, ReflectionMethod $method, AsMessageHandler $attribute): array
    {
        $types = $attribute->handles !== null ? [$attribute->handles] : $this->messageTypes($method);

        return array_map(fn (string $type) => [
            'message' => $type,
            'handler' => $class,
            'options' => array_filter([
                'method' => $method->getName(),
                'bus' => $attribute->bus,
                'from_transport' => $attribute->fromTransport,
                'priority' => $attribute->priority,
            ], fn ($value) => $value !== null),
        ], $types);
    }

    /**
     * @return list<string>
     */
    protected function messageTypes(ReflectionMethod $method): array
    {
        $parameter = $method->getParameters()[0] ?? null;
        $type = $parameter?->getType();

        $types = match (true) {
            $type instanceof ReflectionNamedType => [$type],
            $type instanceof ReflectionUnionType => $type->getTypes(),
            default => [],
        };

        $names = [];

        foreach ($types as $named) {
            if ($named instanceof ReflectionNamedType && ! $named->isBuiltin()) {
                $names[] = $named->getName();
            }
        }

        if ($names === []) {
            throw new LogicException(sprintf(
                'Cannot infer the handled message of %s::%s(). Type-hint the first parameter or use #[AsMessageHandler(handles: Message::class)].',
                $method->class,
                $method->getName(),
            ));
        }

        return $names;
    }

    /**
     * @return iterable<class-string>
     */
    protected function classes(): iterable
    {
        foreach ($this->paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class = self::classFromFile($file->getPathname());

                if ($class !== null && class_exists($class)) {
                    yield $class;
                }
            }
        }
    }

    public static function classFromFile(string $path): ?string
    {
        $source = (string) file_get_contents($path);

        if (! preg_match('/^\s*(?:(?:final|abstract|readonly)\s+)*class\s+(\w+)/m', $source, $class)) {
            return null;
        }

        $namespace = preg_match('/^\s*namespace\s+([^;\s]+)\s*;/m', $source, $match) ? $match[1].'\\' : '';

        return $namespace.$class[1];
    }
}
