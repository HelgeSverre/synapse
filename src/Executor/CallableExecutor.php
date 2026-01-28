<?php

declare(strict_types=1);

namespace LlmExe\Executor;

use LlmExe\Provider\Request\ToolDefinition;
use LlmExe\State\ConversationState;

/**
 * Wraps an executor or callable as a tool function.
 */
final class CallableExecutor
{
    /** @var callable(array<string, mixed>, ConversationState): mixed */
    private $handler;

    /** @var callable(array<string, mixed>, ConversationState): bool|null */
    private $visibilityHandler;

    /** @var callable(array<string, mixed>): array{valid: bool, errors: list<string>}|null */
    private $validateInputHandler;

    /**
     * @param  callable(array<string, mixed>, ConversationState): mixed  $handler
     * @param  array<string, mixed>  $parameters  JSON Schema for parameters
     * @param  array<string, mixed>  $attributes
     * @param  callable(array<string, mixed>, ConversationState): bool|null  $visibilityHandler
     * @param  callable(array<string, mixed>): array{valid: bool, errors: list<string>}|null  $validateInputHandler
     */
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        callable $handler,
        private readonly array $parameters = [],
        private readonly array $attributes = [],
        ?callable $visibilityHandler = null,
        ?callable $validateInputHandler = null,
    ) {
        $this->handler = $handler;
        $this->visibilityHandler = $visibilityHandler;
        $this->validateInputHandler = $validateInputHandler;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(array $input, ?ConversationState $state = null): ToolResult
    {
        $state ??= new ConversationState;

        // Validate input if handler provided
        if ($this->validateInputHandler !== null) {
            $validation = ($this->validateInputHandler)($input);
            if (! $validation['valid']) {
                return new ToolResult(
                    result: null,
                    success: false,
                    errors: $validation['errors'],
                    attributes: $this->attributes,
                );
            }
        }

        try {
            $result = ($this->handler)($input, $state);

            return new ToolResult(
                result: $result,
                success: true,
                errors: [],
                attributes: $this->attributes,
            );
        } catch (\Throwable $e) {
            return new ToolResult(
                result: null,
                success: false,
                errors: [$e->getMessage()],
                attributes: $this->attributes,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{valid: bool, errors: list<string>}
     */
    public function validateInput(array $input): array
    {
        if ($this->validateInputHandler === null) {
            return ['valid' => true, 'errors' => []];
        }

        return ($this->validateInputHandler)($input);
    }

    /**
     * Check if this tool is visible for the given context.
     *
     * @param  array<string, mixed>  $input
     */
    public function isVisible(array $input, ?ConversationState $state = null): bool
    {
        if ($this->visibilityHandler === null) {
            return true;
        }

        return ($this->visibilityHandler)($input, $state ?? new ConversationState);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /** @return array<string, mixed> */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /** @return array<string, mixed> */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function toToolDefinition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name,
            description: $this->description,
            parameters: $this->parameters,
        );
    }
}
