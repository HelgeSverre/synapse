<?php

declare(strict_types=1);

namespace LlmExe\State;

final readonly class ContextItem
{
    public function __construct(
        public string $key,
        public mixed $value,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}
}
