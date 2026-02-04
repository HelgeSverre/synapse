<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Embeddings;

final readonly class EmbeddingResponse
{
    /**
     * @param  array<array<float>>  $embeddings  - List of embedding vectors
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public array $embeddings,
        public string $model,
        public ?int $totalTokens = null,
        public array $raw = [],
    ) {}

    /**
     * Get first embedding (for single input)
     *
     * @return array<float>
     */
    public function getEmbedding(): array
    {
        return $this->embeddings[0] ?? [];
    }
}
