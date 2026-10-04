<?php

declare(strict_types=1);

namespace Ghostwriter\Serializer\Container;

use Ghostwriter\Container\Service\Provider\AbstractProvider;
use Ghostwriter\Serializer\Serializer;
use Ghostwriter\Serializer\SerializerInterface;
use Tests\Unit\Container\SerializerProviderTest;

/**
 * @see SerializerProviderTest
 */
final class SerializerProvider extends AbstractProvider
{
    /**
     * alias => service.
     *
     * @var array<class-string,class-string>
     */
    public const array ALIAS = [
        SerializerInterface::class => Serializer::class,
    ];
}
