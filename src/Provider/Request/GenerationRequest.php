<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Provider\Request;

use HelgeSverre\Synapse\State\Message;

final readonly class GenerationRequest
{
    /**
     * @param  list<Message>  $messages
     * @param  list<ToolDefinition>  $tools
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $model,
        public array $messages,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public array $tools = [],
        public ?string $toolChoice = null,
        public ?array $responseFormat = null,
        public ?string $systemPrompt = null,
        public ?float $topP = null,
        public ?array $stopSequences = null,
        public array $metadata = [],
    ) {}

    /** @param array<string, mixed> $overrides */
    public function with(array $overrides): self
    {
        return new self(
            model: $overrides['model'] ?? $this->model,
            messages: $overrides['messages'] ?? $this->messages,
            temperature: $overrides['temperature'] ?? $this->temperature,
            maxTokens: $overrides['maxTokens'] ?? $this->maxTokens,
            tools: $overrides['tools'] ?? $this->tools,
            toolChoice: $overrides['toolChoice'] ?? $this->toolChoice,
            responseFormat: $overrides['responseFormat'] ?? $this->responseFormat,
            systemPrompt: $overrides['systemPrompt'] ?? $this->systemPrompt,
            topP: $overrides['topP'] ?? $this->topP,
            stopSequences: $overrides['stopSequences'] ?? $this->stopSequences,
            metadata: $overrides['metadata'] ?? $this->metadata,
        );
    }
}
