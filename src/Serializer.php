<?php

declare(strict_types=1);

namespace Ghostwriter\Serializer;

use Closure;
use Generator;
use Ghostwriter\Container\Container;
use Ghostwriter\Container\Interface\ContainerExceptionInterface;
use Ghostwriter\Container\Interface\ContainerInterface;
use Ghostwriter\Container\Interface\Exception\ContainerNotFoundExceptionInterface;
use Ghostwriter\Serializer\Exception\ShouldNotHappenException;
use Ghostwriter\Serializer\Exception\UnexpectedValueException;
use JsonException;
use Override;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use function class_exists;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function json_decode;
use function json_encode;
use function mb_str_split;
use function mb_strlen;
use function preg_match;
use function serialize;
use function sprintf;

/**
 * @see SerializerTest
 */
final readonly class Serializer implements SerializerInterface
{
    public const string CLASS_ARGUMENTS = '__ARGUMENTS__';

    public const string CLASS_NAME = '__CLASS__';

    public function __construct(
        private ContainerInterface $container,
    ) {}

    /** Creates a new instance of the serializer. */
    public static function new(): self
    {
        return Container::getInstance()->get(self::class);
    }

    /**
     * Deserializes a JSON string into an object.
     *
     * @param string $json the JSON string to deserialize
     *
     * @throws SerializerExceptionInterface        if the JSON cannot be deserialized into an object
     * @throws ShouldNotHappenException            if the payload is missing required information or contains invalid data
     * @throws ContainerExceptionInterface         if the container fails to build the object
     * @throws ContainerNotFoundExceptionInterface if the container cannot find the requested class
     *
     * @return object the deserialized object
     */
    #[Override]
    public function deserialize(string $json): object
    {
        $payload = $this->decode($json);

        $class = $payload[self::CLASS_NAME] ?? throw new ShouldNotHappenException('Missing class in payload');

        if (! class_exists($class)) {
            throw new ShouldNotHappenException("Class {$class} does not exist");
        }

        $arguments = $payload[self::CLASS_ARGUMENTS] ?? throw new ShouldNotHappenException(
            'Missing properties in payload'
        );

        if (! is_array($arguments)) {
            throw new ShouldNotHappenException('Arguments must be an array');
        }

        return $this->container->build($class, $this->deserializeArguments($arguments));
    }

    /**
     * Serializes an object into a JSON string.
     *
     * @param object $object the object to serialize
     *
     * @throws JsonException
     * @throws SerializerExceptionInterface if the object cannot be serialized
     *
     * @return string the JSON representation of the object
     */
    #[Override]
    public function serialize(object $object): string
    {
        $serialized = $this->serializeObject($object);

        $generator = $this->split($serialized);

        $objectData = $this->parseObject($generator);

        return $this->encode($objectData);
    }

    /** @throws JsonException */
    private function decode(string $value): array
    {
        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws ContainerNotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    private function deserializeArguments(array $arguments): array
    {
        foreach ($arguments as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (! isset($value[self::CLASS_NAME], $value[self::CLASS_ARGUMENTS])) {
                continue;
            }

            $class = $value[self::CLASS_NAME];
            if (! class_exists($class)) {
                continue;
            }

            $properties = $value[self::CLASS_ARGUMENTS];
            if (! is_array($properties)) {
                continue;
            }

            $arguments[$key] = $this->container->build($class, $this->deserializeArguments($properties));
        }

        return $arguments;
    }

    /** @throws JsonException */
    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @throws UnexpectedValueException */
    private function expect(Generator $generator, string $expectedCharacter): void
    {
        $character = $generator->current() ?? null;
        if ($generator->valid() && $character === $expectedCharacter) {
            $generator->next();

            return;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected character "%s", got "%s" at position "%d".',
            $expectedCharacter,
            $character ?? 'end of string',
            $generator->key() ?? -1
        ));
    }

    /**
     * @param list<string> $characters
     *
     * @throws UnexpectedValueException
     */
    private function expectOne(Generator $generator, array $characters): string
    {
        $character = $generator->current() ?? null;
        if ($generator->valid() && in_array($character, $characters, true)) {
            $generator->next();

            return $character;
        }

        throw new UnexpectedValueException(sprintf(
            'Expected characters "%s", got "%s" at position "%d".',
            implode(' or ', $characters),
            $character ?? 'end of string',
            $generator->key() ?? -1
        ));
    }

    private function parse(Generator $generator, Closure $condition): string
    {
        $buffer = '';

        while ($generator->valid()) {
            $current = $generator->current();

            if (false === $condition($current)) {
                break;
            }

            $buffer .= $current;

            $generator->next();
        }

        return $buffer;
    }

    private function parseArray(Generator $generator): array
    {
        $this->expect($generator, 'a');
        $this->expect($generator, ':');

        $length = $this->parseInt($generator);

        $this->expect($generator, ':');
        $this->expect($generator, '{');

        $array = [];

        if (0 < $length) {
            while ($generator->valid() && $generator->current() !== '}') {
                $key = $this->parseValue($generator);

                $array[$key] = $this->parseValue($generator);
            }
        }

        $this->expect($generator, '}');

        return $array;
    }

    private function parseBoolean(Generator $generator): bool
    {
        $this->expect($generator, 'b');
        $this->expect($generator, ':');

        $boolean = $this->expectOne($generator, ['0', '1']);

        $this->expect($generator, ';');

        return '1' === $boolean;
    }

    private function parseDouble(Generator $generator): float
    {
        $this->expect($generator, 'd');
        $this->expect($generator, ':');

        $float = (float) $this->parseUntil($generator, ';');

        $this->expect($generator, ';');

        return $float;
    }

    private function parseInt(Generator $generator): int
    {
        return (int) $this->parse($generator, is_numeric(...));
    }

    private function parseInteger(Generator $generator): int
    {
        $this->expect($generator, 'i');
        $this->expect($generator, ':');

        $integer = $this->parseInt($generator);

        $this->expect($generator, ';');

        return $integer;
    }

    private function parseNull(Generator $generator): null
    {
        $this->expect($generator, 'N');
        $this->expect($generator, ';');

        return null;
    }

    /** @throws UnexpectedValueException */
    private function parseObject(Generator $generator): array
    {
        $this->expect($generator, 'O');
        $this->expect($generator, ':');

        $length = $this->parseInt($generator);

        $this->expect($generator, ':');
        $this->expect($generator, '"');

        $class = $this->parseUntil($generator, '"');
        if (mb_strlen($class) !== $length) {
            throw new UnexpectedValueException(sprintf(
                'Expected class name of length "%d", got "%d".',
                $length,
                mb_strlen($class)
            ));
        }

        $this->expect($generator, '"');
        $this->expect($generator, ':');

        $size = $this->parseInt($generator);

        $this->expect($generator, ':');
        $this->expect($generator, '{');

        $properties = [];

        while ($generator->valid() && $generator->current() !== '}') {
            $key = $this->parseValue($generator);

            if ($generator->current() === 'r') {
                $this->parseReference($generator);

                --$size;

                continue;
            }

            $properties[$key] = $this->parseValue($generator);
        }

        if (count($properties) !== $size) {
            throw new UnexpectedValueException(sprintf(
                'Expected %d properties, but parsed %d.',
                $size,
                count($properties)
            ));
        }

        return [
            self::CLASS_NAME => $class,
            self::CLASS_ARGUMENTS => $properties,
        ];
    }

    private function parseReference(Generator $generator): int
    {
        // TODO: throw no reference allowed
        $this->expect($generator, 'r');
        $this->expect($generator, ':');

        $referenceId = $this->parseInt($generator);

        $this->expect($generator, ';');

        return $referenceId;
    }

    /** @throws UnexpectedValueException */
    private function parseString(Generator $generator): string
    {
        $this->expect($generator, 's');
        $this->expect($generator, ':');

        $length = $this->parseInt($generator);

        $this->expect($generator, ':');
        $this->expect($generator, '"');

        $string = $this->parseUntil($generator, '"');

        if (mb_strlen($string) !== $length) {
            throw new UnexpectedValueException(sprintf(
                'Expected string of length %d, got %d.',
                $length,
                mb_strlen($string)
            ));
        }

        $this->expect($generator, '"');
        $this->expect($generator, ';');

        return $string;
    }

    private function parseUntil(Generator $generator, string $stop): string
    {
        return $this->parse($generator, static fn (string $character): bool => $character !== $stop);
    }

    /** @throws UnexpectedValueException */
    private function parseValue(Generator $generator): mixed
    {
        $type = $generator->current();

        return match ($type) {
            'b' => $this->parseBoolean($generator),
            's' => $this->parseString($generator),
            'a' => $this->parseArray($generator),
            'O' => $this->parseObject($generator),
            // 'C' => $this->parseCustomObject($generator),
            'N' => $this->parseNull($generator),
            // 'r' => $this->parseReference($generator),
            'i' => $this->parseInteger($generator),
            'd' => $this->parseDouble($generator),
            default => throw new UnexpectedValueException(sprintf('Unsupported value type: "%s"', $type)),
        };
    }

    /** @throws UnexpectedValueException */
    private function serializeObject(object $object): string
    {
        $serializedObject = serialize($object);

        if (1 === preg_match('#^O:\d+:"([^"]+)":\d+:{(.*)}$#ui', $serializedObject, $matches)) {
            return $serializedObject;
        }

        throw new UnexpectedValueException('Failed to parse object from serialized string.');
    }

    private function split(string $text): Generator
    {
        yield from mb_str_split($text, 1, 'UTF-8');
    }
}
