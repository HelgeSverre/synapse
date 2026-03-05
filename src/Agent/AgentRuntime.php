<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Agent;

use Generator;
use HelgeSverre\Synapse\Executor\ExecutionResult;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutor;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutorWithFunctions;
use HelgeSverre\Synapse\Executor\StreamingResult;
use HelgeSverre\Synapse\Factory;
use HelgeSverre\Synapse\Llm;
use HelgeSverre\Synapse\Options\ExecutorOptions;
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\State\ConversationState;
use HelgeSverre\Synapse\Streaming\StreamContext;

final class AgentRuntime implements AgentInterface
{
    public function __construct(
        private readonly LlmProviderInterface|Llm $llm,
        private readonly AgentDefinition $definition,
        private readonly ?ConversationState $state = null,
    ) {}

    public function getDefinition(): AgentDefinition
    {
        return $this->definition;
    }

    public function run(array $input = [], array $history = []): ExecutionResult|StreamingResult
    {
        $executor = Factory::createExecutor(new ExecutorOptions(
            llm: $this->llm,
            prompt: $this->definition->prompt,
            parser: $this->definition->parser,
            model: $this->definition->model,
            temperature: $this->definition->temperature,
            maxTokens: $this->definition->maxTokens,
            tools: $this->definition->tools,
            toolCatalogResolver: $this->definition->toolCatalogResolver,
            stream: $this->definition->stream,
            maxIterations: $this->definition->maxIterations,
            name: $this->definition->name,
            hooks: $this->definition->hooks,
            state: $this->state,
        ));

        if ($this->definition->stream) {
            return $executor->run($input, $history);
        }

        return $executor->run($input, $history);
    }

    public function stream(array $input = [], array $history = [], ?StreamContext $ctx = null): Generator
    {
        if (! $this->definition->stream) {
            throw new \RuntimeException('Agent is not configured for streaming. Set stream=true in AgentDefinition.');
        }

        $executor = Factory::createExecutor(new ExecutorOptions(
            llm: $this->llm,
            prompt: $this->definition->prompt,
            parser: $this->definition->parser,
            model: $this->definition->model,
            temperature: $this->definition->temperature,
            maxTokens: $this->definition->maxTokens,
            tools: $this->definition->tools,
            toolCatalogResolver: $this->definition->toolCatalogResolver,
            stream: true,
            maxIterations: $this->definition->maxIterations,
            name: $this->definition->name,
            hooks: $this->definition->hooks,
            state: $this->state,
        ));

        if (! $executor instanceof StreamingLlmExecutor && ! $executor instanceof StreamingLlmExecutorWithFunctions) {
            throw new \RuntimeException('Expected a streaming executor, got non-streaming executor.');
        }

        return $executor->stream($history === [] ? $input : [...$input, 'history' => $history], $ctx);
    }
}
