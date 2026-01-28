<?php

declare(strict_types=1);

namespace LlmExe\Parser\JsonSchema;

final readonly class ValidationResult
{
    /** @param list<string> $errors */
    public function __construct(
        public bool $valid,
        public array $errors = [],
    ) {}

    public static function success(): self
    {
        return new self(true);
    }

    /** @param list<string> $errors */
    public static function failure(array $errors): self
    {
        return new self(false, $errors);
    }
}
