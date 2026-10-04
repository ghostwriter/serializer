<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use Ghostwriter\Serializer\Exception\ShouldNotHappenException;
use Ghostwriter\Serializer\SerializerExceptionInterface;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Unit\AbstractTestCase;
use Throwable;

use function is_a;

#[CoversClass(ShouldNotHappenException::class)]
final class ShouldNotHappenExceptionTest extends AbstractTestCase
{
    /** @throws Throwable */
    public function testExtendsLogicException(): void
    {
        self::assertTrue(is_a(ShouldNotHappenException::class, LogicException::class, true));
        self::assertTrue(is_a(ShouldNotHappenException::class, Throwable::class, true));
    }

    /** @throws Throwable */
    public function testImplementsExceptionInterface(): void
    {
        self::assertTrue(is_a(ShouldNotHappenException::class, SerializerExceptionInterface::class, true));
    }
}
