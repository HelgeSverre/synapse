# RAG Patterns

Retrieval-Augmented Generation: embed a query, find relevant context, and pass it to the LLM.

## The Pattern

1. **Embed** the user's query using an embedding provider
2. **Search** a vector store for similar documents
3. **Inject** the retrieved context into the prompt
4. **Generate** a response grounded in the retrieved information

## Example

```php
<?php

use function HelgeSverre\Synapse\{
    useLlm, useEmbeddings, createChatPrompt, createParser, createLlmExecutor,
};

$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]);
$embeddings = useEmbeddings('openai', ['apiKey' => getenv('OPENAI_API_KEY')]);

// Step 1: Embed the query
$queryResponse = $embeddings->embed($userQuery, 'text-embedding-3-small');
$queryVector = $queryResponse->getEmbedding();

// Step 2: Search your vector store (pseudo-code)
$relevantDocs = $vectorStore->search($queryVector, limit: 5);
$context = implode("\n\n", array_map(fn($doc) => $doc['content'], $relevantDocs));

// Step 3: Build prompt with retrieved context
$prompt = createChatPrompt()
    ->addSystemMessage(
        "You are a helpful assistant. Answer questions based on the provided context.\n\n" .
        "Context:\n{{context}}"
    )
    ->addUserMessage('{{question}}', parseTemplate: true);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('string'),
    'model' => 'gpt-4o-mini',
]);

// Step 4: Generate with context
$result = $executor->execute([
    'context' => $context,
    'question' => $userQuery,
]);

echo $result->getValue();
```

## Embedding Documents

Build your vector store by embedding documents:

```php
$documents = [
    'PHP is a server-side scripting language.',
    'Laravel is a PHP framework.',
    'Synapse is an LLM orchestration library.',
];

foreach ($documents as $doc) {
    $response = $embeddings->embed($doc, 'text-embedding-3-small');
    $vector = $response->getEmbedding();

    // Store in your vector database
    $vectorStore->insert([
        'content' => $doc,
        'embedding' => $vector,
    ]);
}
```

## Tips

- Use `text-embedding-3-small` for cost-effective embeddings
- Chunk long documents before embedding (e.g., 500-1000 tokens per chunk)
- Include metadata (source, page number) with embedded chunks
- Limit context length to avoid exceeding the model's context window
