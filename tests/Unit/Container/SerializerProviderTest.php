<?php

declare(strict_types=1);

namespace Tests\Unit\Container;

use Ghostwriter\Container\Interface\BuilderInterface;
use Ghostwriter\Container\Interface\ContainerInterface;
use Ghostwriter\Container\Service\Provider\AbstractProvider;
use Ghostwriter\Serializer\Container\SerializerProvider;
use Ghostwriter\Serializer\Serializer;
use Ghostwriter\Serializer\SerializerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Unit\AbstractTestCase;
use Throwable;

use function is_a;

#[CoversClass(SerializerProvider::class)]
final class SerializerProviderTest extends AbstractTestCase
{
    /** @throws Throwable */
    public function testExtendsAbstractProvider(): void
    {
        self::assertTrue(is_a(SerializerProvider::class, AbstractProvider::class, true));
    }

    /** @throws Throwable */
    public function testSerializerProviderRegister(): void
    {
        $builder = $this->createMock(BuilderInterface::class);

        $builder->expects(self::exactly(1))
            ->method('alias')
            ->with(SerializerInterface::class, Serializer::class);

        $container = self::createStub(ContainerInterface::class);

        (new SerializerProvider($container))->register($builder);
    }
}
