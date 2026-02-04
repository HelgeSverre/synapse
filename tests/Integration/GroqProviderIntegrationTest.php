<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Integration;

use GuzzleHttp\Client;
use HelgeSverre\Synapse\Provider\Groq\GroqProvider;
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\TextDelta;
use HelgeSverre\Synapse\Tests\IntegrationTestCase;
use HelgeSverre\Synapse\Tests\RequiresEnv;
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
