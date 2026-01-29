<?php

declare(strict_types=1);

namespace LlmExe\Tests\Integration\Streaming;

use GuzzleHttp\Client;
use LlmExe\Provider\Anthropic\AnthropicProvider;
use LlmExe\Provider\Http\GuzzleStreamTransport;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\Provider\Request\ToolDefinition;
use LlmExe\State\Message;
use LlmExe\Streaming\StreamCompleted;
use LlmExe\Streaming\StreamContext;
use LlmExe\Streaming\TextDelta;
use LlmExe\Streaming\ToolCallsReady;
use LlmExe\Tests\IntegrationTestCase;
use LlmExe\Tests\RequiresEnv;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
#[RequiresEnv('ANTHROPIC_API_KEY')]
final class AnthropicStreamingIntegrationTest extends IntegrationTestCase
{
    private AnthropicProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new Client(['timeout' => 30]);
        $transport = new GuzzleStreamTransport($client);
        $this->provider = new AnthropicProvider($transport, (string) getenv('ANTHROPIC_API_KEY'));
    }

    public function test_stream_basic_text_response(): void
    {
        $request = new GenerationRequest(
            model: 'claude-3-haiku-20240307',
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

        $textDeltas = array_filter($chunks, fn ($e) => $e instanceof TextDelta);
        $this->assertNotEmpty($textDeltas, 'Should receive at least one TextDelta');

        $completed = array_filter($chunks, fn ($e) => $e instanceof StreamCompleted);
        $this->assertCount(1, $completed, 'Should receive exactly one StreamCompleted');

        $this->assertStringContainsStringIgnoringCase('hello', $fullText);
    }

    public function test_stream_with_tool_call(): void
    {
        $request = new GenerationRequest(
            model: 'claude-3-haiku-20240307',
            messages: [Message::user('What is 2 + 2? Use the calculator tool.')],
            maxTokens: 100,
            tools: [
                new ToolDefinition(
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

        $toolCallsReady = array_filter($chunks, fn ($e) => $e instanceof ToolCallsReady);
        $this->assertCount(1, $toolCallsReady, 'Should receive ToolCallsReady');

        $ready = array_values($toolCallsReady)[0];
        $this->assertNotEmpty($ready->toolCalls, 'Should have at least one tool call');
        $this->assertSame('calculator', $ready->toolCalls[0]->name);
    }

    public function test_stream_can_be_cancelled(): void
    {
        $request = new GenerationRequest(
            model: 'claude-3-haiku-20240307',
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

        $textDeltas = array_filter($chunks, fn ($e) => $e instanceof TextDelta);
        $this->assertLessThanOrEqual(5, count($textDeltas), 'Should have stopped after ~3 chunks');
    }
}
