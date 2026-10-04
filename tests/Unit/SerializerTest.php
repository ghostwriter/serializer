<?php

declare(strict_types=1);

namespace Tests\Unit;

use Ghostwriter\Serializer\Serializer;
use Ghostwriter\Serializer\SerializerInterface;
use Person;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Throwable;

use function is_a;
use function json_encode;

#[CoversClass(Serializer::class)]
final class SerializerTest extends AbstractTestCase
{
    /** @throws Throwable */
    public function testParses(): void
    {
        self::assertTrue(is_a(Serializer::class, SerializerInterface::class, true));
        $siblings = [
            ''=>'string',
            0 => null,
            'array' => [1, true, null, false, 0.5],
        ];

        $person = new Person(
            id: 1337,
            name: 'John',
            age: 42,
            null: null,
            boolean: true,
            siblings: $siblings,// , 'array' => [1,true, null, false]
            stdClass: new stdClass(),
        );

        $serializer = Serializer::new();

        $properties = [
            'id' => 1337,
            'name' => 'John',
            'age' => 42,
            'null' => null,
            'boolean' => true,
            'siblings' => $siblings,
            stdClass::class => [
                Serializer::CLASS_NAME => stdClass::class,
                Serializer::CLASS_ARGUMENTS => [],
            ],
        ];

        $payload = [
            Serializer::CLASS_NAME => Person::class,
            Serializer::CLASS_ARGUMENTS => $properties,
        ];

        $serializedJson = $serializer->serialize($person);

        self::assertSame(json_encode($payload), $serializedJson);

        self::assertEquals($person, $serializer->deserialize($serializedJson));
    }
}
