<?php

declare(strict_types=1);

namespace LlmExe\Executor;

final readonly class ToolResult
{
    /**
     * @param  list<string>  $errors
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public mixed $result,
        public bool $success,
        public array $errors = [],
        public array $attributes = [],
    ) {}

    public function toJson(): string
    {
        if (! $this->success) {
            return json_encode(['error' => implode('; ', $this->errors)]) ?: '{"error": "Unknown error"}';
        }

        if (is_string($this->result)) {
            return $this->result;
        }

        return json_encode($this->result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
