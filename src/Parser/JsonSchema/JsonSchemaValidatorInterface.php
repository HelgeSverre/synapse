<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Parser\JsonSchema;

interface JsonSchemaValidatorInterface
{
    /**
     * @param  array<string, mixed>  $schema
     */
    public function validate(mixed $data, array $schema): ValidationResult;
}
