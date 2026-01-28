<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Embeddings\OpenAI;

use LlmExe\Embeddings\EmbeddingResponse;
use LlmExe\Embeddings\OpenAI\OpenAIEmbeddingProvider;
use LlmExe\Provider\Http\TransportInterface;
use PHPUnit\Framework\TestCase;

final class OpenAIEmbeddingProviderTest extends TestCase
{
    private TransportInterface $mockTransport;

    protected function setUp(): void
    {
        $this->mockTransport = $this->createMock(TransportInterface::class);
    }

    public function test_embed_with_single_string_input(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'data' => [
                    ['embedding' => [0.1, 0.2, 0.3]],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => ['total_tokens' => 5],
            ]);

        $provider = new OpenAIEmbeddingProvider($this->mockTransport, 'test-api-key');

        $response = $provider->embed('Hello world', 'text-embedding-3-small');

        $this->assertInstanceOf(EmbeddingResponse::class, $response);
        $this->assertSame([[0.1, 0.2, 0.3]], $response->embeddings);
    }

    public function test_embed_with_array_of_strings(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'data' => [
                    ['embedding' => [0.1, 0.2, 0.3]],
                    ['embedding' => [0.4, 0.5, 0.6]],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => ['total_tokens' => 10],
            ]);

        $provider = new OpenAIEmbeddingProvider($this->mockTransport, 'test-api-key');

        $response = $provider->embed(['Hello', 'World'], 'text-embedding-3-small');

        $this->assertCount(2, $response->embeddings);
        $this->assertSame([0.1, 0.2, 0.3], $response->embeddings[0]);
        $this->assertSame([0.4, 0.5, 0.6], $response->embeddings[1]);
    }

    public function test_correct_url_and_headers(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                'https://api.openai.com/v1/embeddings',
                [
                    'Authorization' => 'Bearer my-secret-key',
                    'Content-Type' => 'application/json',
                ],
                $this->anything(),
            )
            ->willReturn([
                'data' => [['embedding' => [0.1]]],
                'model' => 'text-embedding-3-small',
            ]);

        $provider = new OpenAIEmbeddingProvider($this->mockTransport, 'my-secret-key');
        $provider->embed('test', 'text-embedding-3-small');
    }

    public function test_custom_base_url(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                'https://custom.api.com/v1/embeddings',
                $this->anything(),
                $this->anything(),
            )
            ->willReturn([
                'data' => [['embedding' => [0.1]]],
                'model' => 'text-embedding-3-small',
            ]);

        $provider = new OpenAIEmbeddingProvider(
            $this->mockTransport,
            'test-key',
            'https://custom.api.com/v1',
        );
        $provider->embed('test', 'text-embedding-3-small');
    }

    public function test_dimensions_option_passed_correctly(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body) => $body['dimensions'] === 256),
            )
            ->willReturn([
                'data' => [['embedding' => [0.1]]],
                'model' => 'text-embedding-3-small',
            ]);

        $provider = new OpenAIEmbeddingProvider($this->mockTransport, 'test-key');
        $provider->embed('test', 'text-embedding-3-small', ['dimensions' => 256]);
    }

    public function test_encoding_format_option_passed_correctly(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body) => $body['encoding_format'] === 'base64'),
            )
            ->willReturn([
                'data' => [['embedding' => [0.1]]],
                'model' => 'text-embedding-3-small',
            ]);

        $provider = new OpenAIEmbeddingProvider($this->mockTransport, 'test-key');
        $provider->embed('test', 'text-embedding-3-small', ['encoding_format' => 'base64']);
    }

    public function test_response_parsing(): void
    {
        $rawResponse = [
            'data' => [
                ['embedding' => [0.1, 0.2]],
                ['embedding' => [0.3, 0.4]],
            ],
            'model' => 'text-embedding-3-large',
            'usage' => ['total_tokens' => 15],
        ];

        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->willReturn($rawResponse);

        $provider = new OpenAIEmbeddingProvider($this->mockTransport, 'test-key');
        $response = $provider->embed(['one', 'two'], 'text-embedding-3-large');

        $this->assertSame('text-embedding-3-large', $response->model);
        $this->assertSame(15, $response->totalTokens);
        $this->assertSame([[0.1, 0.2], [0.3, 0.4]], $response->embeddings);
        $this->assertSame($rawResponse, $response->raw);
    }

    public function test_get_embedding_helper(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'data' => [['embedding' => [0.5, 0.6, 0.7]]],
                'model' => 'text-embedding-3-small',
            ]);

        $provider = new OpenAIEmbeddingProvider($this->mockTransport, 'test-key');
        $response = $provider->embed('single input', 'text-embedding-3-small');

        $this->assertSame([0.5, 0.6, 0.7], $response->getEmbedding());
    }

    public function test_get_name_returns_openai(): void
    {
        $provider = new OpenAIEmbeddingProvider($this->mockTransport, 'test-key');

        $this->assertSame('openai', $provider->getName());
    }

    public function test_model_falls_back_to_input_model_when_not_in_response(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'data' => [['embedding' => [0.1]]],
            ]);

        $provider = new OpenAIEmbeddingProvider($this->mockTransport, 'test-key');
        $response = $provider->embed('test', 'my-custom-model');

        $this->assertSame('my-custom-model', $response->model);
    }

    public function test_total_tokens_is_null_when_not_in_response(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'data' => [['embedding' => [0.1]]],
                'model' => 'text-embedding-3-small',
            ]);

        $provider = new OpenAIEmbeddingProvider($this->mockTransport, 'test-key');
        $response = $provider->embed('test', 'text-embedding-3-small');

        $this->assertNull($response->totalTokens);
    }
}
