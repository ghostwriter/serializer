<?php

declare(strict_types=1);

namespace Ghostwriter\Serializer\Exception;

use Ghostwriter\Serializer\SerializerExceptionInterface;

final class UnexpectedValueException extends \UnexpectedValueException implements SerializerExceptionInterface {}
