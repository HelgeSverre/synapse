<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Integration\Embeddings;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use HelgeSverre\Synapse\Embeddings\Ollama\OllamaEmbeddingProvider;
use HelgeSverre\Synapse\Provider\Http\Psr18Transport;
use HelgeSverre\Synapse\Tests\IntegrationTestCase;
use HelgeSverre\Synapse\Tests\RequiresEnv;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
#[RequiresEnv('OLLAMA_BASE_URL')]
final class OllamaEmbeddingIntegrationTest extends IntegrationTestCase
{
    private const MODEL = 'granite-embedding:latest';

    private OllamaEmbeddingProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new Client(['timeout' => 30]);
        $factory = new HttpFactory;
        $transport = new Psr18Transport($client, $factory, $factory);

        $this->provider = new OllamaEmbeddingProvider(
            transport: $transport,
            baseUrl: (string) getenv('OLLAMA_BASE_URL'),
        );
    }

    public function test_embed_single_text(): void
    {
        $response = $this->provider->embed(
            'The quick brown fox jumps over the lazy dog.',
            self::MODEL,
        );

        $this->assertCount(1, $response->embeddings);

        $embedding = $response->getEmbedding();
        $this->assertNotEmpty($embedding);
        $this->assertIsFloat($embedding[0]);
        $this->assertGreaterThan(100, count($embedding), 'Expected an embedding vector with reasonable dimensionality');

        $this->assertSame(self::MODEL, $response->model);
    }

    public function test_embed_multiple_texts(): void
    {
        $response = $this->provider->embed(
            ['Hello world', 'Goodbye world'],
            self::MODEL,
        );

        $this->assertCount(2, $response->embeddings);
        $this->assertNotEmpty($response->embeddings[0]);
        $this->assertNotEmpty($response->embeddings[1]);
        $this->assertSameSize(
            $response->embeddings[0],
            $response->embeddings[1],
            'Batched embeddings should share dimensionality',
        );
    }

    public function test_similar_texts_have_higher_cosine_similarity(): void
    {
        $a = $this->provider->embed('I love programming', self::MODEL)->getEmbedding();
        $b = $this->provider->embed('I enjoy coding', self::MODEL)->getEmbedding();
        $c = $this->provider->embed('The weather is nice today', self::MODEL)->getEmbedding();

        $simAB = $this->cosineSimilarity($a, $b);
        $simAC = $this->cosineSimilarity($a, $c);

        $this->assertGreaterThan(
            $simAC,
            $simAB,
            "Expected semantically similar texts to score higher (sim_ab={$simAB}, sim_ac={$simAC})",
        );
    }

    /**
     * @param  array<float>  $a
     * @param  array<float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $dot += $value * $b[$i];
            $normA += $value * $value;
            $normB += $b[$i] * $b[$i];
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
