<?php

declare(strict_types=1);

namespace LlmExe\Executor;

use Generator;
use LlmExe\Hooks\Events\AfterPromptRender;
use LlmExe\Hooks\Events\BeforePromptRender;
use LlmExe\Hooks\Events\OnComplete;
use LlmExe\Hooks\Events\OnError;
use LlmExe\Hooks\Events\OnStreamChunk;
use LlmExe\Hooks\Events\OnStreamEnd;
use LlmExe\Hooks\Events\OnStreamStart;
use LlmExe\Hooks\Events\OnStreamSuccess;
use LlmExe\Hooks\Events\OnToolCall;
use LlmExe\Hooks\HookDispatcher;
use LlmExe\Hooks\HookDispatcherInterface;
use LlmExe\Prompt\PromptInterface;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\State\ConversationState;
use LlmExe\State\Message;
use LlmExe\Streaming\StreamableProviderInterface;
use LlmExe\Streaming\StreamCompleted;
use LlmExe\Streaming\StreamContext;
use LlmExe\Streaming\StreamEvent;
use LlmExe\Streaming\TextDelta;
use LlmExe\Streaming\ToolCallAccumulator;
use LlmExe\Streaming\ToolCallDelta;
use LlmExe\Streaming\ToolCallsReady;

/**
 * Streaming executor with tool/function calling support.
 *
 * Streams text deltas to consumer in real-time while handling tool calls.
 * When the model requests tool calls, executes them and continues streaming
 * the follow-up response.
 */
final class StreamingLlmExecutorWithFunctions
{
    protected ExecutorMetadata $metadata;

    protected HookDispatcherInterface $hooks;

    protected ConversationState $state;

    public function __construct(
        private readonly StreamableProviderInterface $provider,
        private readonly PromptInterface $prompt,
        private readonly string $model,
        private readonly UseExecutors $tools,
        private readonly int $maxIterations = 10,
        private readonly ?float $temperature = null,
        private readonly ?int $maxTokens = null,
        ?string $name = null,
        ?HookDispatcherInterface $hooks = null,
        ?ConversationState $state = null,
    ) {
        $this->metadata = ExecutorMetadata::create(self::class, $name ?? 'StreamingLlmExecutorWithFunctions');
        $this->hooks = $hooks ?? new HookDispatcher;
        $this->state = $state ?? new ConversationState;
    }

    /**
     * Stream the LLM response, handling tool calls automatically.
     *
     * Yields TextDelta events as they arrive. When the model requests tool calls,
     * executes them and streams the follow-up response. Yields ToolCallsReady
     * before executing tools so consumers can show progress.
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

            $this->hooks->dispatch(new OnStreamStart(new GenerationRequest(
                model: $this->model,
                messages: $messages,
                tools: $this->tools->getToolDefinitions(),
            )));

            $iterations = 0;
            $fullText = '';
            $finalUsage = null;
            $finalFinishReason = null;

            while ($iterations++ < $this->maxIterations) {
                if ($ctx?->shouldCancel()) {
                    break;
                }

                $request = new GenerationRequest(
                    model: $this->model,
                    messages: $messages,
                    temperature: $this->temperature,
                    maxTokens: $this->maxTokens,
                    tools: $this->tools->getToolDefinitions(),
                );

                /** @var TurnResult $turn */
                $turn = yield from $this->streamTurn($request, $ctx, $fullText);

                $fullText .= $turn->assistantText;
                $finalUsage = $turn->usage;
                $finalFinishReason = $turn->finishReason;

                // No tool calls - we're done
                if ($turn->toolCalls === []) {
                    if ($fullText !== '') {
                        $this->state = $this->state->withMessage(Message::assistant($fullText));
                    }

                    $completed = new StreamCompleted(
                        finishReason: $finalFinishReason,
                        usage: $finalUsage,
                    );

                    $this->hooks->dispatch(new OnStreamChunk($completed));
                    $this->hooks->dispatch(new OnStreamEnd($completed, $fullText));

                    yield $completed;

                    $durationMs = (hrtime(true) - $start) / 1_000_000;
                    $result = new StreamingResult(
                        text: $fullText,
                        finishReason: $finalFinishReason,
                        usage: $finalUsage,
                        state: $this->state,
                    );
                    $this->hooks->dispatch(new OnStreamSuccess($result, $durationMs));
                    $this->hooks->dispatch(new OnComplete(true, $durationMs));

                    return;
                }

                // Tool calls present - append assistant message WITH tool calls and execute tools
                $messages[] = Message::assistant($turn->assistantText, $turn->toolCalls);

                // Yield ToolCallsReady so consumers know tools are being called
                $toolCallsReady = new ToolCallsReady($turn->toolCalls);
                $this->hooks->dispatch(new OnStreamChunk($toolCallsReady));
                yield $toolCallsReady;

                // Execute each tool
                foreach ($turn->toolCalls as $toolCall) {
                    if ($ctx?->shouldCancel()) {
                        return;
                    }

                    $this->hooks->dispatch(new OnToolCall($toolCall));

                    $toolResult = $this->tools->callFunction($toolCall->name, $toolCall->arguments);

                    $messages[] = Message::tool(
                        content: is_string($toolResult) ? $toolResult : (json_encode($toolResult) ?: ''),
                        toolCallId: $toolCall->id,
                        name: $toolCall->name,
                    );
                }

                // Continue loop with tool results
            }

            throw new \RuntimeException("Max tool iterations ({$this->maxIterations}) exceeded");
        } catch (\Throwable $e) {
            $durationMs = (hrtime(true) - $start) / 1_000_000;
            $this->hooks->dispatch(new OnError($e));
            $this->hooks->dispatch(new OnComplete(false, $durationMs, $e));
            throw $e;
        }
    }

    /**
     * Stream a single model turn, yielding TextDelta events and returning turn result.
     *
     * @param  string  $fullTextSoFar  Text accumulated from previous turns (for hooks)
     * @return Generator<int, StreamEvent, mixed, TurnResult>
     */
    private function streamTurn(GenerationRequest $request, ?StreamContext $ctx, string $fullTextSoFar): Generator
    {
        $acc = new ToolCallAccumulator;
        $assistantText = '';
        $finishReason = null;
        $usage = null;
        $toolCallsFromProvider = null;

        foreach ($this->provider->stream($request, $ctx) as $event) {
            if ($ctx?->shouldCancel()) {
                return new TurnResult($assistantText, [], $finishReason, $usage);
            }

            // Stream text to consumer immediately
            if ($event instanceof TextDelta) {
                $assistantText .= $event->text;
                $this->hooks->dispatch(new OnStreamChunk($event));
                yield $event;

                continue;
            }

            // Accumulate tool call deltas internally (don't yield)
            if ($event instanceof ToolCallDelta) {
                $acc->add($event);

                continue;
            }

            // Capture complete tool calls from provider
            if ($event instanceof ToolCallsReady) {
                $toolCallsFromProvider = $event->toolCalls;

                continue;
            }

            // Capture completion info but don't yield (outer loop decides final completion)
            if ($event instanceof StreamCompleted) {
                $finishReason = $event->finishReason;
                $usage = $event->usage;

                continue;
            }
        }

        // Prefer provider's tool calls if available, otherwise use accumulated
        $toolCalls = $toolCallsFromProvider ?? ($acc->hasToolCalls() ? $acc->getToolCalls() : []);

        return new TurnResult(
            assistantText: $assistantText,
            toolCalls: $toolCalls,
            finishReason: $finishReason,
            usage: $usage,
        );
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

    public function getTools(): UseExecutors
    {
        return $this->tools;
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
