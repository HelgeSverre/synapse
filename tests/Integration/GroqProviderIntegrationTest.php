<?php

declare(strict_types=1);

namespace LlmExe\Tests\Integration;

use GuzzleHttp\Client;
use LlmExe\Provider\Groq\GroqProvider;
use LlmExe\Provider\Http\GuzzleStreamTransport;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\State\Message;
use LlmExe\Streaming\StreamCompleted;
use LlmExe\Streaming\TextDelta;
use LlmExe\Tests\IntegrationTestCase;
use LlmExe\Tests\RequiresEnv;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
#[RequiresEnv('GROQ_API_KEY')]
final class GroqProviderIntegrationTest extends IntegrationTestCase
{
    private GroqProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new Client(['timeout' => 30]);
        $transport = new GuzzleStreamTransport($client);
        $this->provider = new GroqProvider($transport, (string) getenv('GROQ_API_KEY'));
    }

    public function test_basic_generation(): void
    {
        $request = new GenerationRequest(
            model: 'meta-llama/llama-4-scout-17b-16e-instruct',
            messages: [Message::user('Say "Hello" and nothing else.')],
            maxTokens: 10,
        );

        $response = $this->provider->generate($request);

        $this->assertStringContainsStringIgnoringCase('hello', $response->text ?? '');
        $this->assertNotNull($response->usage);
    }

    public function test_stream_basic_text_response(): void
    {
        $request = new GenerationRequest(
            model: 'meta-llama/llama-4-scout-17b-16e-instruct',
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
    }
}
