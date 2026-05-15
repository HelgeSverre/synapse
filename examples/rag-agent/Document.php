<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\RagAgent;

/**
 * A document in the knowledge base.
 */
final readonly class Document
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $source,
        public string $content,
        public array $metadata = [],
    ) {}

    /**
     * Split document into chunks.
     *
     * @return list<Chunk>
     */
    public function chunk(int $chunkSize = 500, int $overlap = 50): array
    {
        $chunks = [];
        $words = preg_split('/\s+/', trim($this->content)) ?: [];
        $totalWords = count($words);

        if ($totalWords === 0) {
            return [];
        }

        $index = 0;
        $chunkNum = 0;
        $step = max(1, $chunkSize - $overlap);

        while ($index < $totalWords) {
            $chunkWords = array_slice($words, $index, $chunkSize);
            $text = implode(' ', $chunkWords);

            $chunks[] = new Chunk(
                id: "{$this->id}_c{$chunkNum}",
                documentId: $this->id,
                source: $this->source,
                text: $text,
                position: $chunkNum,
            );

            $index += $step;
            $chunkNum++;
        }

        return $chunks;
    }
}
