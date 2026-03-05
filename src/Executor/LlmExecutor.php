<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Executor;

use HelgeSverre\Synapse\Hooks\Events\AfterPromptRender;
use HelgeSverre\Synapse\Hooks\Events\AfterProviderCall;
use HelgeSverre\Synapse\Hooks\Events\BeforePromptRender;
use HelgeSverre\Synapse\Hooks\Events\BeforeProviderCall;
use HelgeSverre\Synapse\Hooks\HookDispatcherInterface;
use HelgeSverre\Synapse\Parser\ParserInterface;
use HelgeSverre\Synapse\Prompt\ChatPrompt;
use HelgeSverre\Synapse\Prompt\PromptInterface;
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\State\ConversationState;
use HelgeSverre\Synapse\State\Message;

/**
 * Orchestrates the full LLM pipeline: Prompt → Provider → Parser.
 *
 * @template T
 *
 * @extends BaseExecutor<array<string, mixed>, T>
 */
class LlmExecutor extends BaseExecutor
{
    /** @var list<Message> */
    protected array $history = [];

    public function __construct(
        protected readonly LlmProviderInterface $provider,
        protected readonly PromptInterface $prompt,
        protected readonly ParserInterface $parser,
        protected readonly string $model,
        protected readonly ?float $temperature = null,
        protected readonly ?int $maxTokens = null,
        protected readonly ?array $responseFormat = null,
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
            responseFormat: $this->responseFormat,
        );

        $this->hooks->dispatch(new BeforeProviderCall($request));

        // Call provider
        $response = $this->provider->generate($request);

        $this->hooks->dispatch(new AfterProviderCall($request, $response));

        // Parse response
        $parsed = $this->parser->parse($response);

        // Update state with assistant message
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

        $history = $this->resolveHistory($input);
        if ($history !== [] && ! $this->containsHistoryMessages($messages, $history)) {
            $messages = [...$history, ...$messages];
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<Message>
     */
    protected function resolveHistory(array $input): array
    {
        $history = $this->history;

        if (isset($input['history']) && is_array($input['history'])) {
            $inputHistory = array_filter($input['history'], fn ($m): bool => $m instanceof Message);
            /** @var list<Message> $inputHistory */
            $inputHistory = array_values($inputHistory);
            $history = [...$history, ...$inputHistory];
        }

        return $history;
    }

    /**
     * @param  list<Message>  $messages
     * @param  list<Message>  $history
     */
    protected function containsHistoryMessages(array $messages, array $history): bool
    {
        if ($messages === [] || $history === []) {
            return false;
        }

        $historyIds = [];
        foreach ($history as $message) {
            $historyIds[spl_object_id($message)] = true;
        }

        foreach ($messages as $message) {
            if (isset($historyIds[spl_object_id($message)])) {
                return true;
            }
        }

        return false;
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

    /**
     * @param  list<Message>  $history
     */
    public function withHistory(array $history): static
    {
        $clone = clone $this;
        $clone->history = $history;

        return $clone;
    }
}
