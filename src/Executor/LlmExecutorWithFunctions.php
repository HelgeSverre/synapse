<?php

declare(strict_types=1);

namespace LlmExe\Executor;

use LlmExe\Hooks\Events\AfterProviderCall;
use LlmExe\Hooks\Events\BeforeProviderCall;
use LlmExe\Hooks\Events\OnToolCall;
use LlmExe\Hooks\HookDispatcherInterface;
use LlmExe\Parser\ParserInterface;
use LlmExe\Prompt\PromptInterface;
use LlmExe\Provider\LlmProviderInterface;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\Provider\Request\ToolDefinition;
use LlmExe\State\ConversationState;
use LlmExe\State\Message;

/**
 * LLM executor with tool/function calling support.
 *
 * @template T
 *
 * @extends LlmExecutor<T>
 */
final class LlmExecutorWithFunctions extends LlmExecutor
{
    private UseExecutors $tools;

    private int $maxIterations;

    /**
     * @param  list<ToolDefinition>  $toolDefinitions
     */
    public function __construct(
        LlmProviderInterface $provider,
        PromptInterface $prompt,
        ParserInterface $parser,
        string $model,
        UseExecutors $tools,
        int $maxIterations = 10,
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
            $name ?? 'LlmExecutorWithFunctions',
            $hooks,
            $state,
        );
        $this->tools = $tools;
        $this->maxIterations = $maxIterations;
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
                if ($response->getAssistantMessage() !== null) {
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
            if ($assistantMessage !== null) {
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
