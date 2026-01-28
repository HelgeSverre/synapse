<?php

declare(strict_types=1);

namespace LlmExe\Executor;

use LlmExe\Hooks\Events\AfterPromptRender;
use LlmExe\Hooks\Events\AfterProviderCall;
use LlmExe\Hooks\Events\BeforePromptRender;
use LlmExe\Hooks\Events\BeforeProviderCall;
use LlmExe\Hooks\HookDispatcherInterface;
use LlmExe\Parser\ParserInterface;
use LlmExe\Prompt\ChatPrompt;
use LlmExe\Prompt\PromptInterface;
use LlmExe\Provider\LlmProviderInterface;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\State\ConversationState;
use LlmExe\State\Message;

/**
 * Orchestrates the full LLM pipeline: Prompt → Provider → Parser.
 *
 * @template T
 *
 * @extends BaseExecutor<array<string, mixed>, T>
 */
class LlmExecutor extends BaseExecutor
{
    public function __construct(
        protected readonly LlmProviderInterface $provider,
        protected readonly PromptInterface $prompt,
        protected readonly ParserInterface $parser,
        protected readonly string $model,
        protected readonly ?float $temperature = null,
        protected readonly ?int $maxTokens = null,
        ?string $name = null,
        ?HookDispatcherInterface $hooks = null,
        ?ConversationState $state = null,
    ) {
        parent::__construct($name ?? 'LlmExecutor', $hooks, $state);
    }

    /** @return ExecutionResult<T> */
    protected function handler(mixed $input): ExecutionResult
    {
        // Dispatch before render event
        $this->hooks->dispatch(new BeforePromptRender($this->prompt, $input));

        // Render prompt
        $rendered = $this->prompt->render($input);
        $this->hooks->dispatch(new AfterPromptRender($rendered));

        // Build messages array
        $messages = $this->buildMessages($rendered, $input);

        // Build request
        $request = new GenerationRequest(
            model: $this->model,
            messages: $messages,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
        );

        $this->hooks->dispatch(new BeforeProviderCall($request));

        // Call provider
        $response = $this->provider->generate($request);

        $this->hooks->dispatch(new AfterProviderCall($request, $response));

        // Parse response
        $parsed = $this->parser->parse($response);

        // Update state with assistant message
        $newState = $this->state;
        if ($response->getAssistantMessage() instanceof \LlmExe\State\Message) {
            $newState = $newState->withMessage($response->getAssistantMessage());
        }
        $this->state = $newState;

        return new ExecutionResult(
            value: $parsed,
            state: $newState,
            response: $response,
        );
    }

    /**
     * @param  string|list<Message>  $rendered
     * @param  array<string, mixed>  $input
     * @return list<Message>
     */
    protected function buildMessages(string|array $rendered, array $input): array
    {
        if (is_array($rendered)) {
            // Already messages from ChatPrompt
            $messages = $rendered;
        } else {
            // Text prompt - wrap in user message
            $messages = [Message::user($rendered)];
        }

        // Include dialogue history from state if present
        $dialogueKey = $input['_dialogueKey'] ?? null;
        if ($dialogueKey !== null && isset($input[$dialogueKey])) {
            $history = $input[$dialogueKey];
            if (is_array($history)) {
                // Prepend history before rendered messages
                $historyMessages = array_filter($history, fn ($m): bool => $m instanceof Message);
                $messages = [...$historyMessages, ...$messages];
            }
        }

        return $messages;
    }

    public function getProvider(): LlmProviderInterface
    {
        return $this->provider;
    }

    public function getPrompt(): PromptInterface
    {
        return $this->prompt;
    }

    public function getParser(): ParserInterface
    {
        return $this->parser;
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
