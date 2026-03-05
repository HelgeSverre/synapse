<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Options;

use HelgeSverre\Synapse\Executor\CallableExecutor;
use HelgeSverre\Synapse\Executor\ToolCatalogResolver;
use HelgeSverre\Synapse\Executor\ToolExecutorInterface;
use HelgeSverre\Synapse\Hooks\HookDispatcherInterface;
use HelgeSverre\Synapse\Llm;
use HelgeSverre\Synapse\Parser\ParserInterface;
use HelgeSverre\Synapse\Prompt\PromptInterface;
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\State\ConversationState;

final readonly class ExecutorOptions
{
    /**
     * @param  array<string, mixed>|null  $responseFormat
     */
    public function __construct(
        public LlmProviderInterface|Llm $llm,
        public PromptInterface $prompt,
        public ?ParserInterface $parser = null,
        public ?string $model = null,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?array $responseFormat = null,
        public ToolExecutorInterface|array|null $tools = null,
        public ?ToolCatalogResolver $toolCatalogResolver = null,
        public bool $stream = false,
        public int $maxIterations = 10,
        public ?string $name = null,
        public ?HookDispatcherInterface $hooks = null,
        public ?ConversationState $state = null,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public static function fromArray(array $options): self
    {
        $tools = $options['tools'] ?? null;

        if (
            ! $tools instanceof ToolExecutorInterface
            && ! is_array($tools)
            && $tools !== null
        ) {
            throw new \InvalidArgumentException('tools must implement ToolExecutorInterface or be an array of CallableExecutor configs');
        }

        if (is_array($tools) && array_is_list($tools)) {
            foreach ($tools as $entry) {
                if (
                    ! $entry instanceof CallableExecutor
                    && ! $entry instanceof CallableExecutorOptions
                    && ! is_array($entry)
                ) {
                    throw new \InvalidArgumentException('tools must implement ToolExecutorInterface or be an array of CallableExecutor configs');
                }
            }
        }

        return new self(
            llm: $options['llm'] ?? throw new \InvalidArgumentException('llm is required'),
            prompt: $options['prompt'] ?? throw new \InvalidArgumentException('prompt is required'),
            parser: $options['parser'] ?? null,
            model: $options['model'] ?? null,
            temperature: $options['temperature'] ?? null,
            maxTokens: $options['maxTokens'] ?? null,
            responseFormat: $options['responseFormat'] ?? null,
            tools: $tools,
            toolCatalogResolver: $options['toolCatalogResolver'] ?? null,
            stream: $options['stream'] ?? false,
            maxIterations: $options['maxIterations'] ?? 10,
            name: $options['name'] ?? null,
            hooks: $options['hooks'] ?? null,
            state: $options['state'] ?? null,
        );
    }
}
