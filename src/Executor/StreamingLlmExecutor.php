<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Executor;

use Generator;
use HelgeSverre\Synapse\Hooks\Events\AfterPromptRender;
use HelgeSverre\Synapse\Hooks\Events\BeforePromptRender;
use HelgeSverre\Synapse\Hooks\Events\OnComplete;
use HelgeSverre\Synapse\Hooks\Events\OnError;
use HelgeSverre\Synapse\Hooks\Events\OnStreamChunk;
use HelgeSverre\Synapse\Hooks\Events\OnStreamEnd;
use HelgeSverre\Synapse\Hooks\Events\OnStreamStart;
use HelgeSverre\Synapse\Hooks\Events\OnStreamSuccess;
use HelgeSverre\Synapse\Hooks\HookDispatcher;
use HelgeSverre\Synapse\Hooks\HookDispatcherInterface;
use HelgeSverre\Synapse\Prompt\PromptInterface;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\State\ConversationState;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\StreamableProviderInterface;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\StreamContext;
use HelgeSverre\Synapse\Streaming\StreamEvent;
use HelgeSverre\Synapse\Streaming\TextDelta;

/**
 * Executor that streams LLM responses.
 *
 * Yields StreamEvent objects as they arrive from the provider.
 * Does not support tool calls - use StreamingLlmExecutorWithFunctions for that.
 */
final class StreamingLlmExecutor
{
    protected ExecutorMetadata $metadata;

    protected HookDispatcherInterface $hooks;

    protected ConversationState $state;

    public function __construct(
        private readonly StreamableProviderInterface $provider,
        private readonly PromptInterface $prompt,
        private readonly string $model,
        private readonly ?float $temperature = null,
        private readonly ?int $maxTokens = null,
        ?string $name = null,
        ?HookDispatcherInterface $hooks = null,
        ?ConversationState $state = null,
    ) {
        $this->metadata = ExecutorMetadata::create(self::class, $name ?? 'StreamingLlmExecutor');
        $this->hooks = $hooks ?? new HookDispatcher;
        $this->state = $state ?? new ConversationState;
    }

    /**
     * Stream the LLM response.
     *
     * @param  array<string, mixed>  $input
     * @return Generator<StreamEvent>
     */
    public function stream(array $input, ?StreamContext $ctx = null): Generator
    {
        $start = hrtime(true);
        $this->metadata = $this->metadata->withExecution();

        try {
            $this->hooks->dispatch(new BeforePromptRender($this->prompt, $input));

            $rendered = $this->prompt->render($input);
            $this->hooks->dispatch(new AfterPromptRender($rendered));

            $messages = $this->buildMessages($rendered, $input);

            $request = new GenerationRequest(
                model: $this->model,
                messages: $messages,
                temperature: $this->temperature,
                maxTokens: $this->maxTokens,
            );

            $this->hooks->dispatch(new OnStreamStart($request));

            $fullText = '';
            $completed = null;

            foreach ($this->provider->stream($request, $ctx) as $event) {
                $this->hooks->dispatch(new OnStreamChunk($event));

                if ($event instanceof TextDelta) {
                    $fullText .= $event->text;
                }

                if ($event instanceof StreamCompleted) {
                    $completed = $event;
                }

                yield $event;
            }

            if ($completed !== null) {
                $this->hooks->dispatch(new OnStreamEnd($completed, $fullText));
            }

            if ($fullText !== '') {
                $this->state = $this->state->withMessage(Message::assistant($fullText));
            }

            $durationMs = (hrtime(true) - $start) / 1_000_000;
            $result = new StreamingResult(
                text: $fullText,
                finishReason: $completed?->finishReason,
                usage: $completed?->usage,
                state: $this->state,
            );
            $this->hooks->dispatch(new OnStreamSuccess($result, $durationMs));
            $this->hooks->dispatch(new OnComplete(true, $durationMs));
        } catch (\Throwable $e) {
            $durationMs = (hrtime(true) - $start) / 1_000_000;
            $this->hooks->dispatch(new OnError($e));
            $this->hooks->dispatch(new OnComplete(false, $durationMs, $e));
            throw $e;
        }
    }

    /**
     * Stream and collect the full text response.
     *
     * @param  array<string, mixed>  $input
     */
    public function streamAndCollect(array $input, ?StreamContext $ctx = null): StreamingResult
    {
        $fullText = '';
        $completed = null;

        foreach ($this->stream($input, $ctx) as $event) {
            if ($event instanceof TextDelta) {
                $fullText .= $event->text;
            }
            if ($event instanceof StreamCompleted) {
                $completed = $event;
            }
        }

        return new StreamingResult(
            text: $fullText,
            finishReason: $completed?->finishReason,
            usage: $completed?->usage,
            state: $this->state,
        );
    }

    /**
     * @param  string|list<Message>  $rendered
     * @param  array<string, mixed>  $input
     * @return list<Message>
     */
    private function buildMessages(string|array $rendered, array $input): array
    {
        if (is_array($rendered)) {
            $messages = $rendered;
        } else {
            $messages = [Message::user($rendered)];
        }

        $dialogueKey = $input['_dialogueKey'] ?? null;
        if ($dialogueKey !== null && isset($input[$dialogueKey])) {
            $history = $input[$dialogueKey];
            if (is_array($history)) {
                $historyMessages = array_filter($history, fn ($m): bool => $m instanceof Message);
                $messages = [...$historyMessages, ...$messages];
            }
        }

        return $messages;
    }

    public function getProvider(): StreamableProviderInterface
    {
        return $this->provider;
    }

    public function getPrompt(): PromptInterface
    {
        return $this->prompt;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getMetadata(): ExecutorMetadata
    {
        return $this->metadata;
    }

    public function getState(): ConversationState
    {
        return $this->state;
    }

    public function withState(ConversationState $state): self
    {
        $clone = clone $this;
        $clone->state = $state;

        return $clone;
    }

    public function withHooks(HookDispatcherInterface $hooks): self
    {
        $clone = clone $this;
        $clone->hooks = $hooks;

        return $clone;
    }

    public function getHooks(): HookDispatcherInterface
    {
        return $this->hooks;
    }

    /** @param callable(object): void $listener */
    public function on(string $eventClass, callable $listener): self
    {
        $this->hooks->addListener($eventClass, $listener);

        return $this;
    }

    public function off(string $eventClass, callable $listener): self
    {
        $this->hooks->removeListener($eventClass, $listener);

        return $this;
    }
}
