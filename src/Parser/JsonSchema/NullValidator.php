<?php

declare(strict_types=1);

namespace LlmExe\Parser\JsonSchema;

final class NullValidator implements JsonSchemaValidatorInterface
{
    public function validate(mixed $data, array $schema): ValidationResult
    {
        return ValidationResult::success();
    }
}
