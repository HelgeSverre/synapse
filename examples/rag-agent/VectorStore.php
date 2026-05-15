<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\RagAgent;

use HelgeSverre\Synapse\Embeddings\EmbeddingProviderInterface;

/**
 * Simple in-memory vector store with cosine similarity search.
 */
final class VectorStore
{
    /** @var array<string, Chunk> */
    private array $chunks = [];

    /** @var array<string, Document> */
    private array $documents = [];

    public function __construct(
        private readonly EmbeddingProviderInterface $embeddingProvider,
        private readonly string $embeddingModel,
    ) {}

    /**
     * Add a document and embed its chunks.
     */
    public function addDocument(Document $document, int $chunkSize = 500): void
    {
        $this->documents[$document->id] = $document;

        $chunks = $document->chunk($chunkSize);

        if (empty($chunks)) {
            return;
        }

        // Embed all chunks in batch
        $texts = array_map(fn (Chunk $c): string => $c->text, $chunks);
        $response = $this->embeddingProvider->embed($texts, $this->embeddingModel);

        foreach ($chunks as $i => $chunk) {
            if (! isset($response->embeddings[$i])) {
                continue;
            }
            $chunk->setEmbedding($response->embeddings[$i]);
            $this->chunks[$chunk->id] = $chunk;
        }
    }

    /**
     * Search for chunks similar to the query.
     *
     * @return list<array{id: string, document_id: string, source: string, text: string, score: float}>
     */
    public function search(string $query, int $topK = 5): array
    {
        if (empty($this->chunks)) {
            return [];
        }

        // Embed query
        $response = $this->embeddingProvider->embed($query, $this->embeddingModel);
        $queryEmbedding = $response->getEmbedding();

        if (empty($queryEmbedding)) {
            return [];
        }

        // Calculate similarities
        $scores = [];
        foreach ($this->chunks as $id => $chunk) {
            if ($chunk->embedding === null) {
                continue;
            }
            $scores[$id] = $this->cosineSimilarity($queryEmbedding, $chunk->embedding);
        }

        // Sort by score descending
        arsort($scores);

        // Return top K
        $results = [];
        $count = 0;
        foreach ($scores as $id => $score) {
            if ($count >= $topK) {
                break;
            }
            $results[] = $this->chunks[$id]->toSearchResult($score);
            $count++;
        }

        return $results;
    }

    /**
     * Get a full document by ID.
     */
    public function getDocument(string $id): ?Document
    {
        return $this->documents[$id] ?? null;
    }

    /**
     * Get a chunk by ID.
     */
    public function getChunk(string $id): ?Chunk
    {
        return $this->chunks[$id] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getDocumentIds(): array
    {
        return array_values(array_keys($this->documents));
    }

    /**
     * @return array{documents: int, chunks: int}
     */
    public function getStats(): array
    {
        return [
            'documents' => count($this->documents),
            'chunks' => count($this->chunks),
        ];
    }

    /**
     * Calculate cosine similarity between two vectors.
     *
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0, $len = count($a); $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }
}
