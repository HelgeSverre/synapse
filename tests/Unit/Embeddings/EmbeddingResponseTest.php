<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Embeddings;

use HelgeSverre\Synapse\Embeddings\EmbeddingResponse;
use PHPUnit\Framework\TestCase;

final class EmbeddingResponseTest extends TestCase
{
    public function test_constructor_with_embeddings_array(): void
    {
        $embeddings = [[0.1, 0.2, 0.3], [0.4, 0.5, 0.6]];

        $response = new EmbeddingResponse(
            embeddings: $embeddings,
            model: 'text-embedding-3-small',
            totalTokens: 10,
            raw: ['data' => 'value'],
        );

        $this->assertSame($embeddings, $response->embeddings);
        $this->assertSame('text-embedding-3-small', $response->model);
        $this->assertSame(10, $response->totalTokens);
        $this->assertSame(['data' => 'value'], $response->raw);
    }

    public function test_get_embedding_returns_first_embedding(): void
    {
        $response = new EmbeddingResponse(
            embeddings: [[0.1, 0.2], [0.3, 0.4], [0.5, 0.6]],
            model: 'test-model',
        );

        $this->assertSame([0.1, 0.2], $response->getEmbedding());
    }

    public function test_get_embedding_returns_empty_array_when_no_embeddings(): void
    {
        $response = new EmbeddingResponse(
            embeddings: [],
            model: 'test-model',
        );

        $this->assertSame([], $response->getEmbedding());
    }

    public function test_total_tokens_defaults_to_null(): void
    {
        $response = new EmbeddingResponse(
            embeddings: [[0.1]],
            model: 'test-model',
        );

        $this->assertNull($response->totalTokens);
    }

    public function test_raw_defaults_to_empty_array(): void
    {
        $response = new EmbeddingResponse(
            embeddings: [[0.1]],
            model: 'test-model',
        );

        $this->assertSame([], $response->raw);
    }

    public function test_all_properties_accessible(): void
    {
        $embeddings = [[1.0, 2.0, 3.0]];
        $raw = ['object' => 'list', 'data' => []];

        $response = new EmbeddingResponse(
            embeddings: $embeddings,
            model: 'ada-002',
            totalTokens: 42,
            raw: $raw,
        );

        $this->assertSame($embeddings, $response->embeddings);
        $this->assertSame('ada-002', $response->model);
        $this->assertSame(42, $response->totalTokens);
        $this->assertSame($raw, $response->raw);
    }
}
