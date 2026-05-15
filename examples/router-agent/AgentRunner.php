<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\RouterAgent;

use Generator;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutor;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutorWithFunctions;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\Streaming\StreamableProviderInterface;
use HelgeSverre\Synapse\Streaming\TextDelta;

/**
 * Runs a specialist agent and collects/yields results.
 */
final class AgentRunner
{
    public function __construct(
        private readonly StreamableProviderInterface $provider,
        private readonly string $model,
        private readonly AgentRegistry $registry,
    ) {}

    /**
     * Run a specialist agent with the given task.
     *
     * @return Generator<AgentStreamEvent>
     */
    public function run(string $agentName, string $task): Generator
    {
        $agent = $this->registry->get($agentName);

        if ($agent === null) {
            yield new AgentStreamEvent(
                type: 'error',
                agentName: $agentName,
                content: "Unknown agent: {$agentName}",
            );

            return;
        }

        yield new AgentStreamEvent(
            type: 'started',
            agentName: $agentName,
            content: "Delegating to {$agentName}...",
        );

        $prompt = (new TextPrompt)->setContent($agent->systemPrompt."\n\n## Task\n{{task}}");

        $executor = $agent->tools !== null
            ? new StreamingLlmExecutorWithFunctions(
                provider: $this->provider,
                prompt: $prompt,
                model: $this->model,
                tools: $agent->tools,
                maxIterations: 5,
                maxTokens: 2048,
            )
            : new StreamingLlmExecutor(
                provider: $this->provider,
                prompt: $prompt,
                model: $this->model,
                maxTokens: 2048,
            );

        $fullOutput = '';

        try {
            foreach ($executor->stream(['task' => $task]) as $event) {
                if ($event instanceof TextDelta) {
                    $fullOutput .= $event->text;
                    yield new AgentStreamEvent(
                        type: 'delta',
                        agentName: $agentName,
                        content: $event->text,
                    );
                }
            }

            yield new AgentStreamEvent(
                type: 'completed',
                agentName: $agentName,
                content: $fullOutput,
            );
        } catch (\Throwable $e) {
            yield new AgentStreamEvent(
                type: 'error',
                agentName: $agentName,
                content: "Agent error: {$e->getMessage()}",
            );
        }
    }
}

/**
 * Event from a running agent.
 */
final readonly class AgentStreamEvent
{
    public function __construct(
        public string $type, // started, delta, completed, error
        public string $agentName,
        public string $content,
    ) {}
}
