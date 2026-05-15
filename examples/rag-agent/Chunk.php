<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\RagAgent;

/**
 * A chunk of text from a document, with its embedding.
 */
final class Chunk
{
    /** @var list<float>|null */
    public ?array $embedding = null;

    public function __construct(
        public readonly string $id,
        public readonly string $documentId,
        public readonly string $source,
        public readonly string $text,
        public readonly int $position = 0,
    ) {}

    /**
     * @param  list<float>  $embedding
     */
    public function setEmbedding(array $embedding): void
    {
        $this->embedding = $embedding;
    }

    /**
     * @return array{id: string, document_id: string, source: string, text: string, score: float}
     */
    public function toSearchResult(float $score): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->documentId,
            'source' => $this->source,
            'text' => $this->text,
            'score' => round($score, 4),
        ];
    }
}
