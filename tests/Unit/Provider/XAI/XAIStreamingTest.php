<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Provider\XAI;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use LlmExe\Provider\Http\StreamTransportInterface;
use LlmExe\Provider\Http\TransportInterface;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\Provider\XAI\XAIProvider;
use LlmExe\State\Message;
use LlmExe\Streaming\StreamableProviderInterface;
use LlmExe\Streaming\StreamCompleted;
use LlmExe\Streaming\StreamContext;
use LlmExe\Streaming\TextDelta;
use LlmExe\Streaming\ToolCallDelta;
use LlmExe\Streaming\ToolCallsReady;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class MockXAIStreamTransport implements StreamTransportInterface
{
    public string $capturedUrl = '';

    /** @var array<string, string> */
    public array $capturedHeaders = [];

    /** @var array<string, mixed> */
    public array $capturedBody = [];

    public function __construct(
        private readonly string $sseData,
        private readonly array $postResponse = [],
    ) {}

    public function post(string $url, array $headers, array $body): array
    {
        return $this->postResponse;
    }

    public function streamPost(string $url, array $headers, array $body, ?StreamContext $ctx = null): ResponseInterface
    {
        $this->capturedUrl = $url;
        $this->capturedHeaders = $headers;
        $this->capturedBody = $body;

        return new Response(
            200,
            ['Content-Type' => 'text/event-stream'],
            Utils::streamFor($this->sseData),
        );
    }
}

final class XAIStreamingTest extends TestCase
{
    public function test_implements_streamable_interface(): void
    {
        $transport = new MockXAIStreamTransport('');
        $provider = new XAIProvider($transport, 'xai-test');

        $this->assertInstanceOf(StreamableProviderInterface::class, $provider);
    }

    public function test_stream_throws_without_stream_transport(): void
    {
        $transport = new class implements TransportInterface
        {
            public function post(string $url, array $headers, array $body): array
            {
                return [];
            }
        };

        $provider = new XAIProvider($transport, 'xai-test');
        $request = new GenerationRequest('grok-beta', [Message::user('Hi')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('StreamTransportInterface');

        iterator_to_array($provider->stream($request));
    }

    public function test_stream_sets_stream_parameter(): void
    {
        $transport = new MockXAIStreamTransport("data: [DONE]\n\n");
        $provider = new XAIProvider($transport, 'xai-test');
        $request = new GenerationRequest('grok-beta', [Message::user('Hi')]);

        iterator_to_array($provider->stream($request));

        $this->assertTrue($transport->capturedBody['stream']);
        $this->assertSame(['include_usage' => true], $transport->capturedBody['stream_options']);
    }

    public function test_stream_yields_text_deltas(): void
    {
        $sse = implode('', [
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{\"role\":\"assistant\",\"content\":\"\"},\"finish_reason\":null}]}\n\n",
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{\"content\":\"Hello\"},\"finish_reason\":null}]}\n\n",
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{\"content\":\" world\"},\"finish_reason\":null}]}\n\n",
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"stop\"}]}\n\n",
            "data: [DONE]\n\n",
        ]);

        $transport = new MockXAIStreamTransport($sse);
        $provider = new XAIProvider($transport, 'xai-test');
        $request = new GenerationRequest('grok-beta', [Message::user('Hi')]);

        $events = iterator_to_array($provider->stream($request), false);

        $textDeltas = array_filter($events, fn ($e) => $e instanceof TextDelta);
        $texts = array_map(fn ($e) => $e->text, $textDeltas);

        $this->assertSame(['Hello', ' world'], array_values($texts));
    }

    public function test_stream_yields_stream_completed(): void
    {
        $sse = implode('', [
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{\"content\":\"Hi\"},\"finish_reason\":null}]}\n\n",
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"stop\"}]}\n\n",
            "data: [DONE]\n\n",
        ]);

        $transport = new MockXAIStreamTransport($sse);
        $provider = new XAIProvider($transport, 'xai-test');
        $request = new GenerationRequest('grok-beta', [Message::user('Hi')]);

        $events = iterator_to_array($provider->stream($request), false);
        $completed = array_filter($events, fn ($e) => $e instanceof StreamCompleted);

        $this->assertCount(1, $completed);
        $this->assertSame('stop', array_values($completed)[0]->finishReason);
    }

    public function test_stream_yields_tool_call_deltas(): void
    {
        $sse = implode('', [
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{\"role\":\"assistant\",\"content\":null,\"tool_calls\":[{\"index\":0,\"id\":\"call_abc\",\"type\":\"function\",\"function\":{\"name\":\"get_weather\",\"arguments\":\"\"}}]},\"finish_reason\":null}]}\n\n",
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{\"tool_calls\":[{\"index\":0,\"function\":{\"arguments\":\"{\\\"city\\\":\"}}]},\"finish_reason\":null}]}\n\n",
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{\"tool_calls\":[{\"index\":0,\"function\":{\"arguments\":\"\\\"Oslo\\\"}\"}}]},\"finish_reason\":null}]}\n\n",
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"tool_calls\"}]}\n\n",
            "data: [DONE]\n\n",
        ]);

        $transport = new MockXAIStreamTransport($sse);
        $provider = new XAIProvider($transport, 'xai-test');
        $request = new GenerationRequest('grok-beta', [Message::user('Weather?')]);

        $events = iterator_to_array($provider->stream($request), false);

        $toolDeltas = array_filter($events, fn ($e) => $e instanceof ToolCallDelta);
        $this->assertCount(3, $toolDeltas);

        $firstDelta = array_values($toolDeltas)[0];
        $this->assertSame(0, $firstDelta->index);
        $this->assertSame('call_abc', $firstDelta->id);
        $this->assertSame('get_weather', $firstDelta->name);
    }

    public function test_stream_yields_tool_calls_ready(): void
    {
        $sse = implode('', [
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{\"tool_calls\":[{\"index\":0,\"id\":\"call_abc\",\"type\":\"function\",\"function\":{\"name\":\"get_weather\",\"arguments\":\"\"}}]},\"finish_reason\":null}]}\n\n",
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{\"tool_calls\":[{\"index\":0,\"function\":{\"arguments\":\"{\\\"city\\\":\\\"Oslo\\\"}\"}}]},\"finish_reason\":null}]}\n\n",
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"tool_calls\"}]}\n\n",
            "data: [DONE]\n\n",
        ]);

        $transport = new MockXAIStreamTransport($sse);
        $provider = new XAIProvider($transport, 'xai-test');
        $request = new GenerationRequest('grok-beta', [Message::user('Weather?')]);

        $events = iterator_to_array($provider->stream($request), false);

        $toolCallsReady = array_filter($events, fn ($e) => $e instanceof ToolCallsReady);
        $this->assertCount(1, $toolCallsReady);

        $ready = array_values($toolCallsReady)[0];
        $this->assertCount(1, $ready->toolCalls);
        $this->assertSame('call_abc', $ready->toolCalls[0]->id);
        $this->assertSame('get_weather', $ready->toolCalls[0]->name);
        $this->assertSame(['city' => 'Oslo'], $ready->toolCalls[0]->arguments);
    }

    public function test_stream_with_usage(): void
    {
        $sse = implode('', [
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{\"content\":\"Hi\"},\"finish_reason\":null}]}\n\n",
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{},\"finish_reason\":\"stop\"}],\"usage\":{\"prompt_tokens\":10,\"completion_tokens\":5,\"total_tokens\":15}}\n\n",
            "data: [DONE]\n\n",
        ]);

        $transport = new MockXAIStreamTransport($sse);
        $provider = new XAIProvider($transport, 'xai-test');
        $request = new GenerationRequest('grok-beta', [Message::user('Hi')]);

        $events = iterator_to_array($provider->stream($request), false);

        $completed = array_values(array_filter($events, fn ($e) => $e instanceof StreamCompleted))[0];
        $this->assertNotNull($completed->usage);
        $this->assertSame(10, $completed->usage->inputTokens);
        $this->assertSame(5, $completed->usage->outputTokens);
        $this->assertSame(15, $completed->usage->totalTokens);
    }

    public function test_stream_calls_correct_url(): void
    {
        $transport = new MockXAIStreamTransport("data: [DONE]\n\n");
        $provider = new XAIProvider($transport, 'xai-test');
        $request = new GenerationRequest('grok-beta', [Message::user('Hi')]);

        iterator_to_array($provider->stream($request), false);

        $this->assertSame('https://api.x.ai/v1/chat/completions', $transport->capturedUrl);
    }

    public function test_stream_sets_authorization_header(): void
    {
        $transport = new MockXAIStreamTransport("data: [DONE]\n\n");
        $provider = new XAIProvider($transport, 'xai-secret-key');
        $request = new GenerationRequest('grok-beta', [Message::user('Hi')]);

        iterator_to_array($provider->stream($request), false);

        $this->assertSame('Bearer xai-secret-key', $transport->capturedHeaders['Authorization']);
    }

    public function test_stream_can_be_cancelled(): void
    {
        $sse = implode('', [
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{\"content\":\"One\"},\"finish_reason\":null}]}\n\n",
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{\"content\":\"Two\"},\"finish_reason\":null}]}\n\n",
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"index\":0,\"delta\":{\"content\":\"Three\"},\"finish_reason\":null}]}\n\n",
            "data: [DONE]\n\n",
        ]);

        $transport = new MockXAIStreamTransport($sse);
        $provider = new XAIProvider($transport, 'xai-test');
        $request = new GenerationRequest('grok-beta', [Message::user('Count')]);

        $count = 0;
        $ctx = new StreamContext(isCancelled: function () use (&$count) {
            return $count >= 2;
        });

        $events = [];
        foreach ($provider->stream($request, $ctx) as $event) {
            $events[] = $event;
            if ($event instanceof TextDelta) {
                $count++;
            }
        }

        $textDeltas = array_filter($events, fn ($e) => $e instanceof TextDelta);
        $this->assertLessThanOrEqual(2, count($textDeltas));
    }
}
