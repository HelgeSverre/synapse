<?php

declare(strict_types=1);

namespace LlmExe\Tests\Integration;

use GuzzleHttp\Client;
use LlmExe\Provider\Google\GoogleProvider;
use LlmExe\Provider\Http\GuzzleStreamTransport;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\Provider\Request\ToolDefinition;
use LlmExe\State\Message;
use LlmExe\Streaming\StreamCompleted;
use LlmExe\Streaming\TextDelta;
use LlmExe\Streaming\ToolCallsReady;
use LlmExe\Tests\IntegrationTestCase;
use LlmExe\Tests\RequiresEnv;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
#[RequiresEnv('GOOGLE_API_KEY')]
final class GoogleProviderIntegrationTest extends IntegrationTestCase
{
    private GoogleProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new Client(['timeout' => 30]);
        $transport = new GuzzleStreamTransport($client);
        $this->provider = new GoogleProvider($transport, (string) getenv('GOOGLE_API_KEY'));
    }

    public function test_basic_generation(): void
    {
        $request = new GenerationRequest(
            model: 'gemini-2.0-flash',
            messages: [Message::user('Say "Hello" and nothing else.')],
            maxTokens: 10,
        );

        $response = $this->provider->generate($request);

        $this->assertNotNull($response->text);
        $this->assertStringContainsStringIgnoringCase('hello', $response->text);
        $this->assertNotNull($response->usage);
        $this->assertGreaterThan(0, $response->usage->inputTokens);
        $this->assertGreaterThan(0, $response->usage->outputTokens);
    }

    public function test_stream_basic_text_response(): void
    {
        $request = new GenerationRequest(
            model: 'gemini-2.0-flash',
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

        $completedEvent = array_values($completed)[0];
        $this->assertNotNull($completedEvent->usage, 'Should have usage info');
        $this->assertGreaterThan(0, $completedEvent->usage->inputTokens);
        $this->assertGreaterThan(0, $completedEvent->usage->outputTokens);
    }

    public function test_stream_with_tool_call(): void
    {
        $request = new GenerationRequest(
            model: 'gemini-2.0-flash',
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
        $this->assertNotEmpty($ready->toolCalls[0]->id, 'Tool call should have an ID');
    }
}
