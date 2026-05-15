<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Examples;

require_once __DIR__.'/../../../examples/rag-agent/Document.php';
require_once __DIR__.'/../../../examples/rag-agent/Chunk.php';
require_once __DIR__.'/../../../examples/rag-agent/SampleKnowledgeBase.php';

use HelgeSverre\Synapse\Examples\RagAgent\Chunk;
use HelgeSverre\Synapse\Examples\RagAgent\Document;
use HelgeSverre\Synapse\Examples\RagAgent\SampleKnowledgeBase;
use PHPUnit\Framework\TestCase;

final class RagAgentTest extends TestCase
{
    public function test_document_chunks_content(): void
    {
        $doc = new Document(
            id: 'test',
            title: 'Test Doc',
            source: 'test.md',
            content: str_repeat('word ', 100), // 100 words
        );

        $chunks = $doc->chunk(chunkSize: 30, overlap: 5);

        $this->assertGreaterThan(1, count($chunks));
        $this->assertSame('test_c0', $chunks[0]->id);
        $this->assertSame('test', $chunks[0]->documentId);
    }

    public function test_chunk_creates_search_result(): void
    {
        $chunk = new Chunk(
            id: 'doc1_c0',
            documentId: 'doc1',
            source: 'docs/test.md',
            text: 'This is test content',
            position: 0,
        );

        $result = $chunk->toSearchResult(0.95);

        $this->assertSame('doc1_c0', $result['id']);
        $this->assertSame('doc1', $result['document_id']);
        $this->assertSame('docs/test.md', $result['source']);
        $this->assertSame(0.95, $result['score']);
    }

    public function test_chunk_stores_embedding(): void
    {
        $chunk = new Chunk('c1', 'd1', 'source', 'text');

        $this->assertNull($chunk->embedding);

        $embedding = [0.1, 0.2, 0.3];
        $chunk->setEmbedding($embedding);

        $this->assertSame($embedding, $chunk->embedding);
    }

    public function test_sample_knowledge_base_has_documents(): void
    {
        $docs = SampleKnowledgeBase::getDocuments();

        $this->assertGreaterThan(0, count($docs));

        $ids = array_map(fn ($d) => $d->id, $docs);
        $this->assertContains('refund_policy', $ids);
        $this->assertContains('api_authentication', $ids);
    }

    public function test_document_has_required_properties(): void
    {
        $doc = new Document(
            id: 'test_doc',
            title: 'Test Title',
            source: 'test/source.md',
            content: 'Test content here',
            metadata: ['author' => 'Test'],
        );

        $this->assertSame('test_doc', $doc->id);
        $this->assertSame('Test Title', $doc->title);
        $this->assertSame('test/source.md', $doc->source);
        $this->assertSame('Test content here', $doc->content);
        $this->assertSame(['author' => 'Test'], $doc->metadata);
    }
}
