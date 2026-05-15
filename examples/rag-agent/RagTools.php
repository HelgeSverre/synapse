<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\RagAgent;

use HelgeSverre\Synapse\Executor\CallableExecutor;
use HelgeSverre\Synapse\State\ConversationState;

/**
 * Tools for RAG: search and document retrieval.
 */
final class RagTools
{
    /** @var array<string, array{source: string, text: string}> Track retrieved chunks */
    private array $retrievedChunks = [];

    public function __construct(
        private readonly VectorStore $vectorStore,
    ) {}

    /**
     * Create the search_knowledge tool.
     */
    public function searchKnowledge(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'search_knowledge',
            description: 'Search the knowledge base for relevant information. Returns chunks with IDs that can be cited.',
            handler: function (array $args, ConversationState $state): string {
                $query = is_string($args['query'] ?? null) ? $args['query'] : '';
                $topK = (int) min((int) ($args['top_k'] ?? 5), 10);

                if ($query === '') {
                    return json_encode(['error' => 'Query is required'], JSON_THROW_ON_ERROR);
                }

                $results = $this->vectorStore->search($query, max(1, $topK));

                // Track retrieved chunks for citation
                foreach ($results as $result) {
                    $this->retrievedChunks[$result['id']] = [
                        'source' => $result['source'],
                        'text' => $result['text'],
                    ];
                }

                return json_encode([
                    'query' => $query,
                    'results_count' => count($results),
                    'results' => $results,
                    'hint' => 'Cite sources using the chunk IDs in brackets, e.g., [doc1_c0]',
                ], JSON_THROW_ON_ERROR);
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'The search query - be specific and use key terms',
                    ],
                    'top_k' => [
                        'type' => 'integer',
                        'description' => 'Number of results to return (default: 5, max: 10)',
                    ],
                ],
                'required' => ['query'],
            ],
        );
    }

    /**
     * Create the get_document tool for full document retrieval.
     */
    public function getDocument(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'get_document',
            description: 'Get the full content of a document by ID. Use when you need more context than the search chunks provide.',
            handler: function (array $args, ConversationState $state): string {
                $docId = is_string($args['document_id'] ?? null) ? $args['document_id'] : '';

                $document = $this->vectorStore->getDocument($docId);

                if ($document === null) {
                    return json_encode([
                        'error' => "Document not found: {$docId}",
                        'available' => $this->vectorStore->getDocumentIds(),
                    ], JSON_THROW_ON_ERROR);
                }

                return json_encode([
                    'id' => $document->id,
                    'title' => $document->title,
                    'source' => $document->source,
                    'content' => $document->content,
                ], JSON_THROW_ON_ERROR);
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'document_id' => [
                        'type' => 'string',
                        'description' => 'The document ID (from search results)',
                    ],
                ],
                'required' => ['document_id'],
            ],
        );
    }

    /**
     * Get all retrieved chunks for citation formatting.
     *
     * @return array<string, array{source: string, text: string}>
     */
    public function getRetrievedChunks(): array
    {
        return $this->retrievedChunks;
    }

    /**
     * Format sources for display.
     */
    public function formatSources(): string
    {
        if (empty($this->retrievedChunks)) {
            return '';
        }

        $lines = ["\n---\n**Sources:**"];

        foreach ($this->retrievedChunks as $id => $chunk) {
            $lines[] = "- [{$id}] {$chunk['source']}";
        }

        return implode("\n", $lines);
    }

    public function reset(): void
    {
        $this->retrievedChunks = [];
    }
}
