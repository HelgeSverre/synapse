<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Provider\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use HelgeSverre\Synapse\Provider\Http\StreamTransportInterface;
use HelgeSverre\Synapse\Provider\Http\TransportInterface;
use HelgeSverre\Synapse\Streaming\StreamContext;
use PHPUnit\Framework\TestCase;

final class GuzzleStreamTransportTest extends TestCase
{
    public function test_implements_interfaces(): void
    {
        $transport = $this->createTransport([
            new Response(200, [], '{}'),
        ]);

        $this->assertInstanceOf(TransportInterface::class, $transport);
        $this->assertInstanceOf(StreamTransportInterface::class, $transport);
    }

    public function test_post_returns_decoded_json(): void
    {
        $transport = $this->createTransport([
            new Response(200, [], '{"result": "success", "value": 42}'),
        ]);

        $result = $transport->post(
            'https://api.example.com/endpoint',
            ['Authorization' => 'Bearer token'],
            ['prompt' => 'Hello'],
        );

        $this->assertSame(['result' => 'success', 'value' => 42], $result);
    }

    public function test_post_throws_on_error_status(): void
    {
        $transport = $this->createTransport([
            new Response(401, [], '{"error": "Unauthorized"}'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 401');

        $transport->post(
            'https://api.example.com/endpoint',
            [],
            [],
        );
    }

    public function test_post_throws_on_invalid_json(): void
    {
        $transport = $this->createTransport([
            new Response(200, [], 'not json'),
        ]);

        $this->expectException(\JsonException::class);

        $transport->post('https://api.example.com/endpoint', [], []);
    }

    public function test_stream_post_returns_response(): void
    {
        $body = "data: {\"text\": \"Hello\"}\n\ndata: [DONE]\n\n";
        $transport = $this->createTransport([
            new Response(200, ['Content-Type' => 'text/event-stream'], $body),
        ]);

        $response = $transport->streamPost(
            'https://api.example.com/stream',
            ['Authorization' => 'Bearer token'],
            ['prompt' => 'Hello', 'stream' => true],
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($body, (string) $response->getBody());
    }

    public function test_stream_post_throws_on_error_status(): void
    {
        $transport = $this->createTransport([
            new Response(500, [], '{"error": "Internal Server Error"}'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');

        $transport->streamPost(
            'https://api.example.com/stream',
            [],
            [],
        );
    }

    public function test_stream_post_with_context_timeout(): void
    {
        $body = "data: {\"text\": \"Hello\"}\n\n";
        $transport = $this->createTransport([
            new Response(200, [], $body),
        ]);

        $ctx = new StreamContext(timeout: 30.0);

        $response = $transport->streamPost(
            'https://api.example.com/stream',
            [],
            [],
            $ctx,
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_stream_post_body_is_readable(): void
    {
        $sseData = implode('', [
            "data: {\"delta\": \"Hello\"}\n",
            "\n",
            "data: {\"delta\": \" World\"}\n",
            "\n",
            "data: [DONE]\n",
            "\n",
        ]);

        $transport = $this->createTransport([
            new Response(200, ['Content-Type' => 'text/event-stream'], $sseData),
        ]);

        $response = $transport->streamPost(
            'https://api.example.com/stream',
            [],
            ['stream' => true],
        );

        $stream = $response->getBody();

        // Read line by line
        $lines = [];
        while (! $stream->eof()) {
            $buffer = '';
            while (! $stream->eof()) {
                $byte = $stream->read(1);
                $buffer .= $byte;
                if ($byte === "\n") {
                    break;
                }
            }
            if ($buffer !== '') {
                $lines[] = $buffer;
            }
        }

        $this->assertCount(6, $lines);
        $this->assertSame("data: {\"delta\": \"Hello\"}\n", $lines[0]);
        $this->assertSame("data: [DONE]\n", $lines[4]);
    }

    private function createTransport(array $responses): GuzzleStreamTransport
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        return new GuzzleStreamTransport($client);
    }
}
