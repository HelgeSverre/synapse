<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Provider\Request;

final readonly class ToolDefinition
{
    public function __construct(
        public string $name,
        public string $description,
        /** @var array<string, mixed> */
        public array $parameters = [],
    ) {}

    /** @return array<string, mixed> */
    public function toOpenAIFormat(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters ?: [
                    'type' => 'object',
                    'properties' => new \stdClass,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function toAnthropicFormat(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'input_schema' => $this->parameters ?: [
                'type' => 'object',
                'properties' => new \stdClass,
            ],
        ];
    }
}
