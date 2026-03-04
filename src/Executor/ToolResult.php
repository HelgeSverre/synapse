<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Executor;

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

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function success(mixed $result, array $attributes = []): self
    {
        return new self(
            result: $result,
            success: true,
            errors: [],
            attributes: $attributes,
        );
    }

    /**
     * @param  list<string>  $errors
     * @param  array<string, mixed>  $attributes
     */
    public static function failure(array $errors, array $attributes = [], mixed $result = null): self
    {
        return new self(
            result: $result,
            success: false,
            errors: $errors,
            attributes: $attributes,
        );
    }

    /** @return array{success: bool, result: mixed, errors: list<string>, attributes: array<string, mixed>} */
    public function toPayload(): array
    {
        return [
            'success' => $this->success,
            'result' => $this->result,
            'errors' => $this->errors,
            'attributes' => $this->attributes,
        ];
    }

    public function toPayloadJson(): string
    {
        return json_encode($this->toPayload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ?: '{"success":false,"result":null,"errors":["Failed to serialize tool payload"],"attributes":{}}';
    }

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
