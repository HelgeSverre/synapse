<?php

declare(strict_types=1);

namespace LlmExe\Provider\Request;

final readonly class ToolCall
{
    public function __construct(
        public string $id,
        public string $name,
        /** @var array<string, mixed> */
        public array $arguments,
    ) {}

    public function getArgumentsJson(): string
    {
        return json_encode($this->arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
