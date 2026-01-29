<?php

declare(strict_types=1);

namespace LlmExe\Tests\Integration\Embeddings;

use GuzzleHttp\Client;
use LlmExe\Embeddings\Mistral\MistralEmbeddingProvider;
use LlmExe\Provider\Http\Psr18Transport;
use LlmExe\Tests\IntegrationTestCase;
use LlmExe\Tests\RequiresEnv;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
#[RequiresEnv('MISTRAL_API_KEY')]
final class MistralEmbeddingIntegrationTest extends IntegrationTestCase
{
    private MistralEmbeddingProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new Client(['timeout' => 30]);
        $transport = new Psr18Transport(
            $client,
            new \GuzzleHttp\Psr7\HttpFactory,
            new \GuzzleHttp\Psr7\HttpFactory,
        );
        $this->provider = new MistralEmbeddingProvider($transport, (string) getenv('MISTRAL_API_KEY'));
    }

    public function test_embed_single_text(): void
    {
        $response = $this->provider->embed(
            'The quick brown fox jumps over the lazy dog.',
            'mistral-embed',
        );

        $this->assertNotEmpty($response->embeddings);
        $this->assertCount(1, $response->embeddings);

        $embedding = $response->getEmbedding();
        $this->assertNotEmpty($embedding);
        $this->assertIsFloat($embedding[0]);

        $this->assertNotNull($response->totalTokens);
        $this->assertGreaterThan(0, $response->totalTokens);
    }

    public function test_embed_multiple_texts(): void
    {
        $response = $this->provider->embed(
            ['Hello world', 'Goodbye world'],
            'mistral-embed',
        );

        $this->assertCount(2, $response->embeddings);
        $this->assertNotEmpty($response->embeddings[0]);
        $this->assertNotEmpty($response->embeddings[1]);
    }

    public function test_similar_texts_have_similar_embeddings(): void
    {
        $response1 = $this->provider->embed('I love programming', 'mistral-embed');
        $response2 = $this->provider->embed('I enjoy coding', 'mistral-embed');
        $response3 = $this->provider->embed('The weather is nice today', 'mistral-embed');

        $sim12 = $this->cosineSimilarity($response1->getEmbedding(), $response2->getEmbedding());
        $sim13 = $this->cosineSimilarity($response1->getEmbedding(), $response3->getEmbedding());

        $this->assertGreaterThan($sim13, $sim12, 'Similar texts should have higher similarity');
    }

    /**
     * @param  array<float>  $a
     * @param  array<float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0, $count = count($a); $i < $count; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}
