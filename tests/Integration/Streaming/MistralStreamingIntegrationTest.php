<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Integration\Streaming;

use GuzzleHttp\Client;
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use HelgeSverre\Synapse\Provider\Mistral\MistralProvider;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Request\ToolDefinition;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\StreamContext;
use HelgeSverre\Synapse\Streaming\TextDelta;
use HelgeSverre\Synapse\Streaming\ToolCallsReady;
use HelgeSverre\Synapse\Tests\IntegrationTestCase;
use HelgeSverre\Synapse\Tests\RequiresEnv;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
#[RequiresEnv('MISTRAL_API_KEY')]
final class MistralStreamingIntegrationTest extends IntegrationTestCase
{
    private MistralProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new Client(['timeout' => 30]);
        $transport = new GuzzleStreamTransport($client);
        $this->provider = new MistralProvider($transport, (string) getenv('MISTRAL_API_KEY'));
    }

    public function test_stream_basic_text_response(): void
    {
        $request = new GenerationRequest(
            model: 'mistral-small-latest',
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
            model: 'mistral-small-latest',
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
            model: 'mistral-small-latest',
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
