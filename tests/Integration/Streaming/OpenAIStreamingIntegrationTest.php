<?php

declare(strict_types=1);

namespace LlmExe\Tests\Integration\Streaming;

use GuzzleHttp\Client;
use LlmExe\Executor\CallableExecutor;
use LlmExe\Executor\StreamingLlmExecutor;
use LlmExe\Executor\StreamingLlmExecutorWithFunctions;
use LlmExe\Executor\UseExecutors;
use LlmExe\Prompt\TextPrompt;
use LlmExe\Provider\Http\GuzzleStreamTransport;
use LlmExe\Provider\OpenAI\OpenAIProvider;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\State\Message;
use LlmExe\Streaming\StreamCompleted;
use LlmExe\Streaming\StreamContext;
use LlmExe\Streaming\TextDelta;
use LlmExe\Streaming\ToolCallsReady;
use LlmExe\Tests\IntegrationTestCase;
use LlmExe\Tests\RequiresEnv;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
#[RequiresEnv('OPENAI_API_KEY')]
final class OpenAIStreamingIntegrationTest extends IntegrationTestCase
{
    private OpenAIProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new Client(['timeout' => 30]);
        $transport = new GuzzleStreamTransport($client);
        $this->provider = new OpenAIProvider($transport, (string) getenv('OPENAI_API_KEY'));
    }

    public function test_stream_basic_text_response(): void
    {
        $request = new GenerationRequest(
            model: 'gpt-4o-mini',
            messages: [Message::user('Say "Hello" and nothing else.')],
            maxTokens: 10,
        );

        $chunks = [];
        $fullText = '';

        foreach ($this->provider->stream($request) as $event) {
            $chunks[] = $event;
            if ($event instanceof TextDelta) {
                $fullText .= $event->text;
            }
        }

        // Should have received text deltas
        $textDeltas = array_filter($chunks, fn ($e) => $e instanceof TextDelta);
        $this->assertNotEmpty($textDeltas, 'Should receive at least one TextDelta');

        // Should end with StreamCompleted
        $completed = array_filter($chunks, fn ($e) => $e instanceof StreamCompleted);
        $this->assertCount(1, $completed, 'Should receive exactly one StreamCompleted');

        // Text should contain "Hello"
        $this->assertStringContainsStringIgnoringCase('hello', $fullText);

        // StreamCompleted should have usage info
        $completedEvent = array_values($completed)[0];
        $this->assertNotNull($completedEvent->usage, 'Should have usage info');
        $this->assertGreaterThan(0, $completedEvent->usage->inputTokens);
        $this->assertGreaterThan(0, $completedEvent->usage->outputTokens);
    }

    public function test_stream_with_tool_call(): void
    {
        $request = new GenerationRequest(
            model: 'gpt-4o-mini',
            messages: [Message::user('What is 2 + 2? Use the calculator tool.')],
            maxTokens: 100,
            tools: [
                new \LlmExe\Provider\Request\ToolDefinition(
                    name: 'calculator',
                    description: 'Performs arithmetic calculations',
                    parameters: [
                        'type' => 'object',
                        'properties' => [
                            'expression' => [
                                'type' => 'string',
                                'description' => 'The math expression to evaluate',
                            ],
                        ],
                        'required' => ['expression'],
                    ],
                ),
            ],
        );

        $chunks = [];

        foreach ($this->provider->stream($request) as $event) {
            $chunks[] = $event;
        }

        // Should have ToolCallsReady
        $toolCallsReady = array_filter($chunks, fn ($e) => $e instanceof ToolCallsReady);
        $this->assertCount(1, $toolCallsReady, 'Should receive ToolCallsReady');

        $ready = array_values($toolCallsReady)[0];
        $this->assertNotEmpty($ready->toolCalls, 'Should have at least one tool call');
        $this->assertSame('calculator', $ready->toolCalls[0]->name);
        $this->assertNotEmpty($ready->toolCalls[0]->id, 'Tool call should have an ID');
    }

    public function test_stream_can_be_cancelled(): void
    {
        $request = new GenerationRequest(
            model: 'gpt-4o-mini',
            messages: [Message::user('Count from 1 to 100, one number per line.')],
            maxTokens: 500,
        );

        $counter = new class
        {
            public int $count = 0;
        };

        $ctx = new StreamContext(isCancelled: static function () use ($counter): bool {
            return $counter->count >= 3;
        });

        $chunks = [];
        foreach ($this->provider->stream($request, $ctx) as $event) {
            $chunks[] = $event;
            if ($event instanceof TextDelta) {
                $counter->count++;
            }
        }

        // Should have stopped early
        $textDeltas = array_filter($chunks, fn ($e) => $e instanceof TextDelta);
        $this->assertLessThanOrEqual(5, count($textDeltas), 'Should have stopped after ~3 chunks');
    }

    public function test_streaming_executor_basic(): void
    {
        $prompt = (new TextPrompt)->setContent('Say "test" and nothing else.');
        $executor = new StreamingLlmExecutor(
            $this->provider,
            $prompt,
            'gpt-4o-mini',
            maxTokens: 10,
        );

        $result = $executor->streamAndCollect([]);

        $this->assertStringContainsStringIgnoringCase('test', $result->text);
        $this->assertNotNull($result->usage);
    }

    public function test_streaming_executor_with_tools(): void
    {
        $tools = new UseExecutors([
            new CallableExecutor(
                name: 'get_weather',
                description: 'Get the current weather for a city',
                handler: fn (array $input) => json_encode([
                    'city' => $input['city'] ?? 'unknown',
                    'temperature' => 22,
                    'condition' => 'sunny',
                ]),
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'city' => ['type' => 'string', 'description' => 'City name'],
                    ],
                    'required' => ['city'],
                ],
            ),
        ]);

        $prompt = (new TextPrompt)->setContent('What is the weather in Oslo? Use the get_weather tool, then tell me the result.');
        $executor = new StreamingLlmExecutorWithFunctions(
            $this->provider,
            $prompt,
            'gpt-4o-mini',
            $tools,
            maxTokens: 150,
        );

        $events = [];
        foreach ($executor->stream([]) as $event) {
            $events[] = $event;
        }

        // Should have called the tool
        $toolCallsReady = array_filter($events, fn ($e) => $e instanceof ToolCallsReady);
        $this->assertNotEmpty($toolCallsReady, 'Should have called get_weather tool');

        // Should have final response mentioning weather
        $textDeltas = array_filter($events, fn ($e) => $e instanceof TextDelta);
        $fullText = implode('', array_map(fn ($e) => $e->text, $textDeltas));
        $this->assertNotEmpty($fullText, 'Should have response text');

        // Check it mentions something weather-related
        $this->assertTrue(
            str_contains(strtolower($fullText), 'sunny') ||
            str_contains(strtolower($fullText), '22') ||
            str_contains(strtolower($fullText), 'oslo') ||
            str_contains(strtolower($fullText), 'weather'),
            "Response should mention weather info: {$fullText}",
        );
    }
}
