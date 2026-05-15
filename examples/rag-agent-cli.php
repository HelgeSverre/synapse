<?php

declare(strict_types=1);

/**
 * RAG Agent Demo
 *
 * Demonstrates an agent that:
 * 1. Searches a knowledge base using embeddings
 * 2. Retrieves relevant document chunks
 * 3. Answers questions with citations
 * 4. Supports multi-hop retrieval
 *
 * Usage:
 *   php examples/rag-agent-cli.php [provider]
 *
 * Provider options: openai (default), anthropic
 *
 * Requires:
 *   - OPENAI_API_KEY for embeddings (text-embedding-3-small)
 *     or set EMBEDDING_PROVIDER=ollama to use the local Ollama embedding
 *     provider with the granite-embedding:latest model.
 *   - OPENAI_API_KEY or ANTHROPIC_API_KEY for the chat model.
 */

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/rag-agent/Document.php';
require_once __DIR__.'/rag-agent/Chunk.php';
require_once __DIR__.'/rag-agent/VectorStore.php';
require_once __DIR__.'/rag-agent/RagTools.php';
require_once __DIR__.'/rag-agent/SampleKnowledgeBase.php';

use GuzzleHttp\Client;
use HelgeSverre\Synapse\Examples\RagAgent\RagTools;
use HelgeSverre\Synapse\Examples\RagAgent\SampleKnowledgeBase;
use HelgeSverre\Synapse\Examples\RagAgent\VectorStore;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutorWithFunctions;
use HelgeSverre\Synapse\Executor\ToolRegistry;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\Provider\Anthropic\AnthropicProvider;
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use HelgeSverre\Synapse\Provider\OpenAI\OpenAIProvider;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\TextDelta;
use HelgeSverre\Synapse\Streaming\ToolCallsReady;

use function HelgeSverre\Synapse\useEmbeddings;

const CYAN = "\033[36m";
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const RED = "\033[31m";
const MAGENTA = "\033[35m";
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";

function createProvider(string $name, GuzzleStreamTransport $transport): array
{
    return match ($name) {
        'openai' => [
            new OpenAIProvider($transport, getenv('OPENAI_API_KEY') ?: exit(RED."OPENAI_API_KEY not set\n".RESET)),
            'gpt-4o-mini',
        ],
        'anthropic' => [
            new AnthropicProvider($transport, getenv('ANTHROPIC_API_KEY') ?: exit(RED."ANTHROPIC_API_KEY not set\n".RESET)),
            'claude-3-haiku-20240307',
        ],
        default => exit(RED."Unknown provider: {$name}\n".RESET),
    };
}

// Banner
echo BOLD.CYAN.'
╔═══════════════════════════════════════════════════════╗
║              RAG Agent Demo                           ║
╚═══════════════════════════════════════════════════════╝'.RESET.'

This agent searches a knowledge base and answers with citations.

'.BOLD.'Knowledge Base Contents:'.RESET.'
  - Refund Policy
  - API Authentication Guide
  - Shipping Information
  - Privacy Policy

'.BOLD.'Example questions:'.RESET.'
  - "What\'s the refund policy for digital products?"
  - "How do I authenticate API requests?"
  - "What are the shipping options to Europe?"
  - "How long do you keep my data?"

'.DIM.'Loading knowledge base and generating embeddings...'.RESET."\n";

// Setup
$providerName = $argv[1] ?? 'openai';
$guzzle = new Client(['timeout' => 120]);
$transport = new GuzzleStreamTransport($guzzle);

[$provider, $model] = createProvider($providerName, $transport);

// Create embedding provider via the factory.
// Default to OpenAI; switch to Ollama (granite-embedding:latest) by setting
// EMBEDDING_PROVIDER=ollama for a local-only setup.
$embeddingProviderName = getenv('EMBEDDING_PROVIDER') ?: 'openai';

if ($embeddingProviderName === 'ollama') {
    $embeddingProvider = useEmbeddings('ollama', [
        'baseUrl' => getenv('OLLAMA_BASE_URL') ?: 'http://localhost:11434/v1',
    ]);
    $embeddingModel = getenv('OLLAMA_EMBEDDING_MODEL') ?: 'granite-embedding:latest';
} else {
    $openaiKey = getenv('OPENAI_API_KEY');
    if (! $openaiKey) {
        echo RED."OPENAI_API_KEY is required for embeddings (or set EMBEDDING_PROVIDER=ollama)\n".RESET;
        exit(1);
    }
    $embeddingProvider = useEmbeddings('openai', ['apiKey' => $openaiKey]);
    $embeddingModel = 'text-embedding-3-small';
}

$vectorStore = new VectorStore($embeddingProvider, $embeddingModel);

// Load sample documents
$documents = SampleKnowledgeBase::getDocuments();
foreach ($documents as $doc) {
    echo DIM."  Indexing: {$doc->title}...".RESET."\n";
    $vectorStore->addDocument($doc, chunkSize: 200);
}

$stats = $vectorStore->getStats();
echo GREEN."Indexed {$stats['documents']} documents ({$stats['chunks']} chunks)".RESET."\n";
echo CYAN.'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━'.RESET."\n\n";

// Create RAG tools
$ragTools = new RagTools($vectorStore);
$tools = new ToolRegistry([
    $ragTools->searchKnowledge(),
    $ragTools->getDocument(),
]);

// System prompt
$systemPrompt = <<<'PROMPT'
You are a helpful assistant that answers questions using a knowledge base.

## Instructions
1. Use the search_knowledge tool to find relevant information
2. If you need more context, use get_document to retrieve full documents
3. Always cite your sources using chunk IDs in brackets like [refund_policy_c0]
4. If the knowledge base doesn't contain relevant information, say so clearly
5. Be concise but thorough

## Multi-hop Retrieval
If your first search doesn't find enough information, try:
- Different search terms
- More specific queries
- Retrieving the full document for more context
PROMPT;

$prompt = (new TextPrompt)->setContent('{{message}}');
$messages = [Message::system($systemPrompt)];

// Main loop
while (true) {
    echo BOLD.GREEN.'Question: '.RESET;
    $input = trim(fgets(STDIN) ?: '');

    if ($input === '' || $input === '/exit') {
        echo DIM.'Goodbye!'.RESET."\n";
        break;
    }

    // Reset citation tracking
    $ragTools->reset();

    $messages[] = Message::user($input);

    $executor = new StreamingLlmExecutorWithFunctions(
        provider: $provider,
        prompt: $prompt,
        model: $model,
        tools: $tools,
        maxIterations: 5,
        maxTokens: 1024,
    );

    echo "\n".BOLD.CYAN.'Answer: '.RESET;

    $responseText = '';

    try {
        foreach ($executor->stream([
            'message' => $input,
            'history' => array_slice($messages, 0, -1),
        ]) as $event) {
            if ($event instanceof TextDelta) {
                echo $event->text;
                $responseText .= $event->text;
                flush();
            }

            if ($event instanceof ToolCallsReady) {
                echo DIM."\n[Searching...]".RESET."\n";
            }

            if ($event instanceof StreamCompleted) {
                // Done
            }
        }
    } catch (\Throwable $e) {
        echo "\n".RED.'Error: '.$e->getMessage().RESET."\n";
        array_pop($messages);

        continue;
    }

    // Show sources
    $sources = $ragTools->formatSources();
    if ($sources !== '') {
        echo DIM.$sources.RESET;
    }

    if ($responseText !== '') {
        $messages[] = Message::assistant($responseText);
    }

    echo "\n\n";
}
