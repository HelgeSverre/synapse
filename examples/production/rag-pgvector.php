<?php

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createExecutor;
use function HelgeSverre\Synapse\createParser;

use HelgeSverre\Synapse\Options\ExecutorOptions;

use function HelgeSverre\Synapse\useLlm;

/**
 * Placeholder RAG pattern for pgvector:
 * 1. Fetch top-k chunks from pgvector.
 * 2. Inject chunks as context.
 * 3. Ask model to cite chunk ids.
 */
$chunks = [
    ['id' => 'doc-101', 'text' => 'Synapse provides prompt, parser, and executor abstractions.'],
    ['id' => 'doc-202', 'text' => 'Tool calling executors can loop with maxIterations to complete tasks.'],
];

$context = implode("\n\n", array_map(fn (array $c): string => "[{$c['id']}] {$c['text']}", $chunks));

$llm = useLlm('openai', ['apiKey' => getenv('OPENAI_API_KEY'), 'model' => 'gpt-4o-mini']);
$executor = createExecutor(new ExecutorOptions(
    llm: $llm,
    prompt: createChatPrompt()
        ->addSystemMessage('Answer only from context and cite chunk ids in brackets.')
        ->addUserMessage("Context:\n{{context}}\n\nQuestion: {{question}}", parseTemplate: true),
    parser: createParser('string'),
));

$result = $executor->run([
    'context' => $context,
    'question' => 'How does Synapse handle tool loops?',
]);

echo $result->getValue()."\n";
