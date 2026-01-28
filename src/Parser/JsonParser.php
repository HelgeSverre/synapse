<?php

declare(strict_types=1);

namespace LlmExe\Parser;

use LlmExe\Parser\JsonSchema\JsonSchemaValidatorInterface;
use LlmExe\Parser\JsonSchema\NullValidator;
use LlmExe\Provider\Response\GenerationResponse;

/**
 * @template T of array<string, mixed>
 *
 * @extends BaseParser<T>
 */
final class JsonParser extends BaseParser
{
    public function __construct(
        /** @var array<string, mixed>|null */
        private readonly ?array $schema = null,
        private readonly bool $validateSchema = false,
        private readonly ?JsonSchemaValidatorInterface $validator = null,
    ) {
        parent::__construct();
    }

    /** @return T */
    public function parse(GenerationResponse $response): array
    {
        $text = trim($this->getTextContent($response));

        // Try to extract JSON from markdown code blocks
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $text, $matches)) {
            $text = trim($matches[1]);
        }

        $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new \InvalidArgumentException('Parsed JSON is not an object/array');
        }

        /** @var T $data */
        if ($this->validateSchema && $this->schema !== null) {
            $validator = $this->validator ?? new NullValidator;
            $result = $validator->validate($data, $this->schema);

            if (! $result->valid) {
                throw new \InvalidArgumentException(
                    'JSON Schema validation failed: '.implode(', ', $result->errors),
                );
            }
        }

        return $data;
    }

    /** @return array<string, mixed>|null */
    public function getSchema(): ?array
    {
        return $this->schema;
    }
}
