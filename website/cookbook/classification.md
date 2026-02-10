# Classification

Classify text into predefined categories using the enum or JSON parser.

## With Enum Parser

The simplest approach for single-label classification:

```php
<?php

use function HelgeSverre\Synapse\{useLlm, createChatPrompt, createParser, createLlmExecutor};

$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]);

$prompt = createChatPrompt()
    ->addSystemMessage(
        'You are an intent classifier. Classify the user message into one of these ' .
        'categories: greeting, question, complaint, request, other. ' .
        'Respond with the category name only.'
    )
    ->addUserMessage('{{message}}', parseTemplate: true);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('enum', [
        'values' => ['greeting', 'question', 'complaint', 'request', 'other'],
    ]),
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute(['message' => 'Your product broke after one day!']);
echo $result->getValue(); // "complaint"
```

## With JSON Parser (Multi-Label)

For richer classification with confidence scores:

```php
$prompt = createChatPrompt()
    ->addSystemMessage(
        'Classify the sentiment of the given text. Respond with JSON containing ' .
        '"sentiment" (positive, negative, neutral) and "confidence" (0.0-1.0).'
    )
    ->addUserMessage('{{text}}', parseTemplate: true);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('json', [
        'schema' => [
            'type' => 'object',
            'properties' => [
                'sentiment' => ['type' => 'string'],
                'confidence' => ['type' => 'number'],
            ],
        ],
    ]),
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute(['text' => 'I absolutely love this product!']);
// ['sentiment' => 'positive', 'confidence' => 0.95]
```

## With Boolean Parser (Binary)

For yes/no classification:

```php
$prompt = createChatPrompt()
    ->addSystemMessage('Is this message spam? Answer "yes" or "no" only.')
    ->addUserMessage('{{message}}', parseTemplate: true);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('boolean'),
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute(['message' => 'BUY NOW! Limited time offer!!!']);
$result->getValue(); // true (is spam)
```
