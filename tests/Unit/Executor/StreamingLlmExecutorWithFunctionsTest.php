<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Executor;

use Generator;
use HelgeSverre\Synapse\Executor\CallableExecutor;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutorWithFunctions;
use HelgeSverre\Synapse\Executor\StreamingResult;
use HelgeSverre\Synapse\Executor\UseExecutors;
use HelgeSverre\Synapse\Hooks\Events\OnStreamChunk;
use HelgeSverre\Synapse\Hooks\Events\OnToolCall;
use HelgeSverre\Synapse\Hooks\HookDispatcher;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\Provider\Response\UsageInfo;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\StreamableProviderInterface;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\StreamContext;
use HelgeSverre\Synapse\Streaming\StreamEvent;
use HelgeSverre\Synapse\Streaming\TextDelta;
use HelgeSverre\Synapse\Streaming\ToolCallDelta;
use HelgeSverre\Synapse\Streaming\ToolCallsReady;
use PHPUnit\Framework\TestCase;

/**
 * Mock provider that returns different event sequences per call (turn).
 */
final class MultiTurnMockProvider implements StreamableProviderInterface
{
    /** @var list<list<StreamEvent>> */
    public array $turns = [];

    /** @var list<GenerationRequest> */
    public array $capturedRequests = [];

    private int $turnIndex = 0;

    public function generate(GenerationRequest $request): GenerationResponse
    {
        return new GenerationResponse(text: 'Hello', messages: [Message::assistant('Hello')]);
    }

    public function stream(GenerationRequest $request, ?StreamContext $ctx = null): Generator
    {
        $this->capturedRequests[] = $request;

        $events = $this->turns[$this->turnIndex] ?? [];
        $this->turnIndex++;

        foreach ($events as $event) {
            if ($ctx?->shouldCancel()) {
                return;
            }
            yield $event;
        }
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(supportsStreaming: true, supportsTools: true);
    }

    public function getName(): string
    {
        return 'multi-turn-mock';
    }
}

final class StreamingLlmExecutorWithFunctionsTest extends TestCase
{
    private function createPrompt(string $content): TextPrompt
    {
        return (new TextPrompt)->setContent($content);
    }

    private function createMockTool(string $name, string $returnValue): CallableExecutor
    {
        return new CallableExecutor(
            name: $name,
            description: "Mock tool: {$name}",
            handler: fn (array $input) => $returnValue,
            parameters: ['type' => 'object', 'properties' => []],
        );
    }

    private function createRecordingTool(string $name, array &$calls, string $returnValue): CallableExecutor
    {
        return new CallableExecutor(
            name: $name,
            description: "Recording tool: {$name}",
            handler: function (array $input) use (&$calls, $name, $returnValue) {
                $calls[] = ['name' => $name, 'input' => $input];

                return $returnValue;
            },
            parameters: ['type' => 'object', 'properties' => []],
        );
    }

    // ================== Happy Path Tests ==================

    public function test_simple_response_without_tool_calls(): void
    {
        $provider = new MultiTurnMockProvider;
        $provider->turns = [
            [
                new TextDelta('Hello'),
                new TextDelta(' world'),
                new StreamCompleted('stop'),
            ],
        ];

        $tools = new UseExecutors([$this->createMockTool('test_tool', 'result')]);
        $prompt = $this->createPrompt('Say hi');
        $executor = new StreamingLlmExecutorWithFunctions($provider, $prompt, 'gpt-4', $tools);

        $events = iterator_to_array($executor->stream([]), false);

        $textDeltas = array_filter($events, fn ($e) => $e instanceof TextDelta);
        $texts = array_map(fn ($e) => $e->text, $textDeltas);

        $this->assertSame(['Hello', ' world'], array_values($texts));
        $this->assertCount(1, array_filter($events, fn ($e) => $e instanceof StreamCompleted));
    }

    public function test_tool_loop_happy_path(): void
    {
        $provider = new MultiTurnMockProvider;

        // Turn 1: text + tool call
        $provider->turns[] = [
            new TextDelta('Checking weather...'),
            new ToolCallDelta(0, 'call_123', 'get_weather', ''),
            new ToolCallDelta(0, null, null, '{"city":"Oslo"}'),
            new StreamCompleted('tool_calls'),
        ];

        // Turn 2: final response
        $provider->turns[] = [
            new TextDelta('The weather in Oslo is sunny.'),
            new StreamCompleted('stop'),
        ];

        $toolCalls = [];
        $tools = new UseExecutors([
            $this->createRecordingTool('get_weather', $toolCalls, '{"temperature": 20, "condition": "sunny"}'),
        ]);

        $prompt = $this->createPrompt('What is the weather?');
        $executor = new StreamingLlmExecutorWithFunctions($provider, $prompt, 'gpt-4', $tools);

        $events = iterator_to_array($executor->stream([]), false);

        // Verify streamed text
        $textDeltas = array_filter($events, fn ($e) => $e instanceof TextDelta);
        $fullText = implode('', array_map(fn ($e) => $e->text, $textDeltas));
        $this->assertSame('Checking weather...The weather in Oslo is sunny.', $fullText);

        // Verify tool was called
        $this->assertCount(1, $toolCalls);
        $this->assertSame('get_weather', $toolCalls[0]['name']);
        $this->assertSame(['city' => 'Oslo'], $toolCalls[0]['input']);

        // Verify ToolCallsReady was emitted
        $toolCallsReady = array_filter($events, fn ($e) => $e instanceof ToolCallsReady);
        $this->assertCount(1, $toolCallsReady);

        // Verify only one final StreamCompleted
        $completed = array_filter($events, fn ($e) => $e instanceof StreamCompleted);
        $this->assertCount(1, $completed);
    }

    public function test_mixed_text_and_tool_calls_in_same_turn(): void
    {
        $provider = new MultiTurnMockProvider;

        // Turn 1: both text AND tool call
        $provider->turns[] = [
            new TextDelta('Let me check that for you. '),
            new ToolCallDelta(0, 'call_abc', 'lookup', ''),
            new ToolCallDelta(0, null, null, '{"id":42}'),
            new StreamCompleted('tool_calls'),
        ];

        // Turn 2: final response
        $provider->turns[] = [
            new TextDelta('Found it!'),
            new StreamCompleted('stop'),
        ];

        $tools = new UseExecutors([
            $this->createMockTool('lookup', '{"found": true}'),
        ]);

        $prompt = $this->createPrompt('Find item 42');
        $executor = new StreamingLlmExecutorWithFunctions($provider, $prompt, 'gpt-4', $tools);

        iterator_to_array($executor->stream([]));

        // Verify second request includes assistant message with text before tool result
        $this->assertCount(2, $provider->capturedRequests);
        $secondRequest = $provider->capturedRequests[1];

        // Messages should be: user, assistant (with text), tool result
        $messages = $secondRequest->messages;
        $this->assertGreaterThanOrEqual(3, count($messages));

        // Find the assistant message
        $assistantMessages = array_filter($messages, fn ($m) => $m->role->value === 'assistant');
        $this->assertNotEmpty($assistantMessages);
        $assistantMsg = array_values($assistantMessages)[0];
        $this->assertSame('Let me check that for you. ', $assistantMsg->content);
    }

    public function test_multiple_tool_calls_in_single_turn(): void
    {
        $provider = new MultiTurnMockProvider;

        // Turn 1: two tool calls
        $provider->turns[] = [
            new ToolCallDelta(0, 'call_1', 'func_a', ''),
            new ToolCallDelta(0, null, null, '{"x":1}'),
            new ToolCallDelta(1, 'call_2', 'func_b', ''),
            new ToolCallDelta(1, null, null, '{"y":2}'),
            new StreamCompleted('tool_calls'),
        ];

        // Turn 2: final response
        $provider->turns[] = [
            new TextDelta('Both done.'),
            new StreamCompleted('stop'),
        ];

        $toolCalls = [];
        $tools = new UseExecutors([
            $this->createRecordingTool('func_a', $toolCalls, 'result_a'),
            $this->createRecordingTool('func_b', $toolCalls, 'result_b'),
        ]);

        $prompt = $this->createPrompt('Do both');
        $executor = new StreamingLlmExecutorWithFunctions($provider, $prompt, 'gpt-4', $tools);

        iterator_to_array($executor->stream([]));

        // Both tools should have been called
        $this->assertCount(2, $toolCalls);
        $this->assertSame('func_a', $toolCalls[0]['name']);
        $this->assertSame('func_b', $toolCalls[1]['name']);

        // Second request should have two tool result messages
        $secondRequest = $provider->capturedRequests[1];
        $toolMessages = array_filter($secondRequest->messages, fn ($m) => $m->role->value === 'tool');
        $this->assertCount(2, $toolMessages);
    }

    // ================== Cancellation Tests ==================

    public function test_cancellation_stops_streaming(): void
    {
        $provider = new MultiTurnMockProvider;
        $provider->turns[] = [
            new TextDelta('One'),
            new TextDelta('Two'),
            new TextDelta('Three'),
            new StreamCompleted('stop'),
        ];

        $tools = new UseExecutors([]);
        $prompt = $this->createPrompt('Count');
        $executor = new StreamingLlmExecutorWithFunctions($provider, $prompt, 'gpt-4', $tools);

        $count = 0;
        $ctx = new StreamContext(isCancelled: function () use (&$count) {
            return $count >= 2;
        });

        $events = [];
        foreach ($executor->stream([], $ctx) as $event) {
            $events[] = $event;
            if ($event instanceof TextDelta) {
                $count++;
            }
        }

        $textDeltas = array_filter($events, fn ($e) => $e instanceof TextDelta);
        $this->assertLessThanOrEqual(2, count($textDeltas));
    }

    public function test_cancellation_before_tool_execution(): void
    {
        $provider = new MultiTurnMockProvider;
        $provider->turns[] = [
            new TextDelta('Let me call the tool'),
            new ToolCallDelta(0, 'call_1', 'expensive_tool', '{}'),
            new StreamCompleted('tool_calls'),
        ];

        $toolCalled = false;
        $tool = new CallableExecutor(
            name: 'expensive_tool',
            description: 'Expensive',
            handler: function () use (&$toolCalled) {
                $toolCalled = true;

                return 'result';
            },
        );

        $tools = new UseExecutors([$tool]);
        $prompt = $this->createPrompt('Do expensive thing');
        $executor = new StreamingLlmExecutorWithFunctions($provider, $prompt, 'gpt-4', $tools);

        // Cancel after receiving ToolCallsReady
        $ctx = new StreamContext(isCancelled: function () use (&$events) {
            return count(array_filter($events ?? [], fn ($e) => $e instanceof ToolCallsReady)) > 0;
        });

        $events = [];
        foreach ($executor->stream([], $ctx) as $event) {
            $events[] = $event;
        }

        // Tool should not have been called (cancelled before execution)
        $this->assertFalse($toolCalled);
    }

    // ================== Max Iterations Tests ==================

    public function test_max_iterations_throws_exception(): void
    {
        $provider = new MultiTurnMockProvider;

        // Always return tool calls
        for ($i = 0; $i < 15; $i++) {
            $provider->turns[] = [
                new ToolCallDelta(0, "call_{$i}", 'infinite_tool', '{}'),
                new StreamCompleted('tool_calls'),
            ];
        }

        $tools = new UseExecutors([
            $this->createMockTool('infinite_tool', 'still going'),
        ]);

        $prompt = $this->createPrompt('Loop forever');
        $executor = new StreamingLlmExecutorWithFunctions(
            $provider,
            $prompt,
            'gpt-4',
            $tools,
            maxIterations: 3,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Max tool iterations (3) exceeded');

        iterator_to_array($executor->stream([]));
    }

    // ================== Hook Tests ==================

    public function test_dispatches_tool_call_hooks(): void
    {
        $provider = new MultiTurnMockProvider;
        $provider->turns[] = [
            new ToolCallDelta(0, 'call_1', 'my_tool', '{"arg":"value"}'),
            new StreamCompleted('tool_calls'),
        ];
        $provider->turns[] = [
            new TextDelta('Done'),
            new StreamCompleted('stop'),
        ];

        $tools = new UseExecutors([
            $this->createMockTool('my_tool', 'result'),
        ]);

        $prompt = $this->createPrompt('Use tool');
        $hooks = new HookDispatcher;

        $receivedToolCalls = [];
        $hooks->addListener(OnToolCall::class, function ($e) use (&$receivedToolCalls): void {
            $receivedToolCalls[] = $e->toolCall;
        });

        $executor = new StreamingLlmExecutorWithFunctions(
            $provider,
            $prompt,
            'gpt-4',
            $tools,
            hooks: $hooks,
        );

        iterator_to_array($executor->stream([]));

        $this->assertCount(1, $receivedToolCalls);
        $this->assertSame('my_tool', $receivedToolCalls[0]->name);
        $this->assertSame(['arg' => 'value'], $receivedToolCalls[0]->arguments);
    }

    public function test_dispatches_stream_chunk_hooks(): void
    {
        $provider = new MultiTurnMockProvider;
        $provider->turns[] = [
            new TextDelta('Hello'),
            new StreamCompleted('stop'),
        ];

        $tools = new UseExecutors([]);
        $prompt = $this->createPrompt('Say hi');
        $hooks = new HookDispatcher;

        $chunkTypes = [];
        $hooks->addListener(OnStreamChunk::class, function ($e) use (&$chunkTypes): void {
            $chunkTypes[] = get_class($e->event);
        });

        $executor = new StreamingLlmExecutorWithFunctions(
            $provider,
            $prompt,
            'gpt-4',
            $tools,
            hooks: $hooks,
        );

        iterator_to_array($executor->stream([]));

        $this->assertContains(TextDelta::class, $chunkTypes);
        $this->assertContains(StreamCompleted::class, $chunkTypes);
    }

    // ================== Request Verification Tests ==================

    public function test_includes_tools_in_request(): void
    {
        $provider = new MultiTurnMockProvider;
        $provider->turns[] = [
            new TextDelta('Hi'),
            new StreamCompleted('stop'),
        ];

        $tools = new UseExecutors([
            $this->createMockTool('tool_one', 'r1'),
            $this->createMockTool('tool_two', 'r2'),
        ]);

        $prompt = $this->createPrompt('Hi');
        $executor = new StreamingLlmExecutorWithFunctions($provider, $prompt, 'gpt-4', $tools);

        iterator_to_array($executor->stream([]));

        $this->assertCount(1, $provider->capturedRequests);
        $this->assertCount(2, $provider->capturedRequests[0]->tools);
    }

    public function test_tool_result_messages_include_tool_call_id(): void
    {
        $provider = new MultiTurnMockProvider;
        $provider->turns[] = [
            new ToolCallDelta(0, 'call_xyz_123', 'my_tool', '{}'),
            new StreamCompleted('tool_calls'),
        ];
        $provider->turns[] = [
            new TextDelta('Done'),
            new StreamCompleted('stop'),
        ];

        $tools = new UseExecutors([
            $this->createMockTool('my_tool', 'result'),
        ]);

        $prompt = $this->createPrompt('Call tool');
        $executor = new StreamingLlmExecutorWithFunctions($provider, $prompt, 'gpt-4', $tools);

        iterator_to_array($executor->stream([]));

        $secondRequest = $provider->capturedRequests[1];
        $toolMessages = array_values(array_filter($secondRequest->messages, fn ($m) => $m->role->value === 'tool'));

        $this->assertCount(1, $toolMessages);
        $this->assertSame('call_xyz_123', $toolMessages[0]->toolCallId);
        $this->assertSame('my_tool', $toolMessages[0]->name);
    }

    // ================== Utility Method Tests ==================

    public function test_stream_and_collect_returns_full_text(): void
    {
        $provider = new MultiTurnMockProvider;
        $provider->turns[] = [
            new TextDelta('Part 1. '),
            new ToolCallDelta(0, 'call_1', 'tool', '{}'),
            new StreamCompleted('tool_calls'),
        ];
        $provider->turns[] = [
            new TextDelta('Part 2.'),
            new StreamCompleted('stop', new UsageInfo(100, 50, 150)),
        ];

        $tools = new UseExecutors([
            $this->createMockTool('tool', 'result'),
        ]);

        $prompt = $this->createPrompt('Test');
        $executor = new StreamingLlmExecutorWithFunctions($provider, $prompt, 'gpt-4', $tools);

        $result = $executor->streamAndCollect([]);

        $this->assertInstanceOf(StreamingResult::class, $result);
        $this->assertSame('Part 1. Part 2.', $result->text);
        $this->assertSame('stop', $result->finishReason);
        $this->assertNotNull($result->usage);
        $this->assertSame(100, $result->usage->inputTokens);
    }

    public function test_updates_state_after_completion(): void
    {
        $provider = new MultiTurnMockProvider;
        $provider->turns[] = [
            new TextDelta('Final answer'),
            new StreamCompleted('stop'),
        ];

        $tools = new UseExecutors([]);
        $prompt = $this->createPrompt('Question');
        $executor = new StreamingLlmExecutorWithFunctions($provider, $prompt, 'gpt-4', $tools);

        iterator_to_array($executor->stream([]));

        $state = $executor->getState();
        $this->assertCount(1, $state->messages);
        $this->assertSame('Final answer', $state->messages[0]->content);
    }

    // ================== Accessor Tests ==================

    public function test_get_tools_returns_tools(): void
    {
        $provider = new MultiTurnMockProvider;
        $tools = new UseExecutors([
            $this->createMockTool('a', 'r'),
        ]);
        $prompt = $this->createPrompt('Hi');

        $executor = new StreamingLlmExecutorWithFunctions($provider, $prompt, 'gpt-4', $tools);

        $this->assertSame($tools, $executor->getTools());
    }

    public function test_with_state_returns_clone(): void
    {
        $provider = new MultiTurnMockProvider;
        $tools = new UseExecutors([]);
        $prompt = $this->createPrompt('Hi');

        $executor = new StreamingLlmExecutorWithFunctions($provider, $prompt, 'gpt-4', $tools);
        $newState = $executor->getState()->withMessage(Message::user('test'));
        $newExecutor = $executor->withState($newState);

        $this->assertNotSame($executor, $newExecutor);
        $this->assertCount(1, $newExecutor->getState()->messages);
        $this->assertCount(0, $executor->getState()->messages);
    }
}
