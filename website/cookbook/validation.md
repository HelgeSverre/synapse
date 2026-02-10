# LLM Validation

Use LLMs to validate data that's hard to check with traditional rules.

## The Pattern

Use the boolean parser to get yes/no validation from an LLM.

## Example: Content Quality Check

```php
<?php

use function HelgeSverre\Synapse\{useLlm, createChatPrompt, createParser, createLlmExecutor};

$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]);

$prompt = createChatPrompt()
    ->addSystemMessage(
        'You are a content quality validator. Check if the given product description ' .
        'meets these criteria:' . "\n" .
        '1. At least 2 sentences long' . "\n" .
        '2. Describes the product clearly' . "\n" .
        '3. Free of grammatical errors' . "\n" .
        '4. Professional tone' . "\n" .
        'Answer "yes" if it meets ALL criteria, "no" otherwise.'
    )
    ->addUserMessage('{{description}}', parseTemplate: true);

$validator = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('boolean'),
]);

$result = $validator->execute([
    'description' => 'good product buy now',
]);
echo $result->getValue(); // false

$result = $validator->execute([
    'description' => 'The Synapse library provides a modern, composable approach to ' .
                     'LLM orchestration in PHP. It supports multiple providers and ' .
                     'includes built-in parsers for structured output.',
]);
echo $result->getValue(); // true
```

## Example: Validation with Feedback

Use JSON parser to get both a verdict and explanation:

```php
$prompt = createChatPrompt()
    ->addSystemMessage(
        'Validate the given data and respond with JSON: ' .
        '{"valid": true/false, "issues": ["issue1", "issue2"]}'
    )
    ->addUserMessage('Validate this email: {{email}}', parseTemplate: true);

$validator = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('json'),
]);

$result = $validator->execute(['email' => 'not-an-email']);
// ['valid' => false, 'issues' => ['Missing @ symbol', 'No domain']]
```
