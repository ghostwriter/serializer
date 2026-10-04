<?php

declare(strict_types=1);

final class Person
{
    public self $self;
    public function __construct(
        public int $id,
        public string $name,
        public float $age,
        public null $null,
        public bool $boolean,
        public array $siblings,
        public stdClass $stdClass,
    ) {
        $this->self = $this;
    }
}
