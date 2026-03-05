<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit;

use Generator;
use HelgeSverre\Synapse\Llm;
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\StreamableProviderInterface;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\StreamContext;
use PHPUnit\Framework\TestCase;

final class LlmTest extends TestCase
{
    public function test_stream_delegates_for_streamable_provider(): void
    {
        $provider = new class implements LlmProviderInterface, StreamableProviderInterface
        {
            public function generate(GenerationRequest $request): GenerationResponse
            {
                return new GenerationResponse('ok', [Message::assistant('ok')], [], $request->model);
            }

            public function stream(GenerationRequest $request, ?StreamContext $ctx = null): Generator
            {
                yield new StreamCompleted('stop');
            }

            public function getCapabilities(): ProviderCapabilities
            {
                return new ProviderCapabilities(supportsStreaming: true);
            }

            public function getName(): string
            {
                return 'streamable';
            }
        };

        $llm = new Llm($provider, 'test-model');
        $request = new GenerationRequest(model: 'test-model', messages: [Message::user('hi')]);

        $events = iterator_to_array($llm->stream($request));

        $this->assertCount(1, $events);
        $this->assertInstanceOf(StreamCompleted::class, $events[0]);
    }

    public function test_stream_throws_for_non_streamable_provider(): void
    {
        $provider = new class implements LlmProviderInterface
        {
            public function generate(GenerationRequest $request): GenerationResponse
            {
                return new GenerationResponse('ok', [Message::assistant('ok')], [], $request->model);
            }

            public function getCapabilities(): ProviderCapabilities
            {
                return new ProviderCapabilities;
            }

            public function getName(): string
            {
                return 'sync-only';
            }
        };

        $llm = new Llm($provider, 'test-model');
        $request = new GenerationRequest(model: 'test-model', messages: [Message::user('hi')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not support streaming');

        iterator_to_array($llm->stream($request));
    }
}
