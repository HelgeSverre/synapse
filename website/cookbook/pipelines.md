# Chaining Executors

Compose multiple executors into a pipeline where the output of one feeds into the next.

## The Pattern

Synapse executors can be chained manually — the `ExecutionResult` from one executor provides input for the next.

## Example: Summarize → Extract Keywords → Classify

```php
<?php

use function HelgeSverre\Synapse\{
    useLlm, createChatPrompt, createParser, createLlmExecutor, createCoreExecutor,
};

$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]);

// Step 1: Summarize
$summarizer = createLlmExecutor([
    'llm' => $llm,
    'prompt' => createChatPrompt()
        ->addSystemMessage('Summarize the following text in 2-3 sentences.')
        ->addUserMessage('{{text}}', parseTemplate: true),
    'parser' => createParser('string'),
    'model' => 'gpt-4o-mini',
]);

// Step 2: Extract keywords
$keywordExtractor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => createChatPrompt()
        ->addSystemMessage('Extract the top 5 keywords from this text. One per line.')
        ->addUserMessage('{{text}}', parseTemplate: true),
    'parser' => createParser('list'),
    'model' => 'gpt-4o-mini',
]);

// Step 3: Classify topic
$classifier = createLlmExecutor([
    'llm' => $llm,
    'prompt' => createChatPrompt()
        ->addSystemMessage(
            'Based on these keywords, classify the topic as: ' .
            'technology, science, business, sports, entertainment, other. ' .
            'Respond with the topic only.'
        )
        ->addUserMessage('Keywords: {{keywords}}', parseTemplate: true),
    'parser' => createParser('enum', [
        'values' => ['technology', 'science', 'business', 'sports', 'entertainment', 'other'],
    ]),
    'model' => 'gpt-4o-mini',
]);

// Run the pipeline
$article = "Long article text...";

$summary = $summarizer->execute(['text' => $article]);
$keywords = $keywordExtractor->execute(['text' => $summary->getValue()]);
$topic = $classifier->execute(['keywords' => implode(', ', $keywords->getValue())]);

echo "Summary: " . $summary->getValue() . "\n";
echo "Keywords: " . implode(', ', $keywords->getValue()) . "\n";
echo "Topic: " . $topic->getValue() . "\n";
```

## Using CoreExecutor for Non-LLM Steps

Mix LLM and non-LLM steps:

```php
// Non-LLM step: clean input
$cleaner = createCoreExecutor(fn($input) => [
    'text' => strip_tags(html_entity_decode($input['html'])),
]);

// LLM step: analyze
$analyzer = createLlmExecutor([...]);

// Pipeline
$cleaned = $cleaner->execute(['html' => $rawHtml]);
$analysis = $analyzer->execute(['text' => $cleaned->getValue()['text']]);
```
