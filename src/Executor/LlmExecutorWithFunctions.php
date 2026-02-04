<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Executor;

use HelgeSverre\Synapse\Hooks\Events\AfterProviderCall;
use HelgeSverre\Synapse\Hooks\Events\BeforeProviderCall;
use HelgeSverre\Synapse\Hooks\Events\OnToolCall;
use HelgeSverre\Synapse\Hooks\HookDispatcherInterface;
use HelgeSverre\Synapse\Parser\ParserInterface;
use HelgeSverre\Synapse\Prompt\PromptInterface;
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Request\ToolDefinition;
use HelgeSverre\Synapse\State\ConversationState;
use HelgeSverre\Synapse\State\Message;

/**
 * LLM executor with tool/function calling support.
 *
 * @template T
 *
 * @extends LlmExecutor<T>
 */
final class LlmExecutorWithFunctions extends LlmExecutor
{
    /**
     * @param  list<ToolDefinition>  $toolDefinitions
     */
    public function __construct(
        LlmProviderInterface $provider,
        PromptInterface $prompt,
        ParserInterface $parser,
        string $model,
        private readonly UseExecutors $tools,
        private readonly int $maxIterations = 10,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?string $name = null,
        ?HookDispatcherInterface $hooks = null,
        ?ConversationState $state = null,
    ) {
        parent::__construct(
            $provider,
            $prompt,
            $parser,
            $model,
            $temperature,
            $maxTokens,
            null, // responseFormat not used with functions
            $name ?? 'LlmExecutorWithFunctions',
            $hooks,
            $state,
        );
    }

    protected function handler(mixed $input): ExecutionResult
    {
        // Initial prompt render
        $rendered = $this->prompt->render($input);
        $messages = $this->buildMessages($rendered, $input);

        $iterations = 0;

        while ($iterations < $this->maxIterations) {
            $iterations++;

            // Build request with tools
            $request = new GenerationRequest(
                model: $this->model,
                messages: $messages,
                temperature: $this->temperature,
                maxTokens: $this->maxTokens,
                tools: $this->tools->getToolDefinitions(),
            );

            $this->hooks->dispatch(new BeforeProviderCall($request));

            $response = $this->provider->generate($request);

            $this->hooks->dispatch(new AfterProviderCall($request, $response));

            // Check for tool calls
            if (! $response->hasToolCalls()) {
                // No tool calls - parse and return final response
                $parsed = $this->parser->parse($response);

                $newState = $this->state;
                if ($response->getAssistantMessage() instanceof \HelgeSverre\Synapse\State\Message) {
                    $newState = $newState->withMessage($response->getAssistantMessage());
                }
                $this->state = $newState;

                return new ExecutionResult(
                    value: $parsed,
                    state: $newState,
                    response: $response,
                );
            }

            // Handle tool calls
            $assistantMessage = $response->getAssistantMessage();
            if ($assistantMessage instanceof \HelgeSverre\Synapse\State\Message) {
                $messages[] = $assistantMessage;
            }

            foreach ($response->getToolCalls() as $toolCall) {
                $this->hooks->dispatch(new OnToolCall($toolCall));

                // Execute the tool
                $toolResult = $this->tools->callFunction($toolCall->name, $toolCall->arguments);

                // Add tool result message
                $messages[] = Message::tool(
                    content: is_string($toolResult) ? $toolResult : (json_encode($toolResult) ?: ''),
                    toolCallId: $toolCall->id,
                    name: $toolCall->name,
                );
            }
        }

        throw new \RuntimeException("Max tool iterations ({$this->maxIterations}) exceeded");
    }

    public function getTools(): UseExecutors
    {
        return $this->tools;
    }
}
