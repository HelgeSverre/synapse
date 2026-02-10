# Self-Refinement

Generate output, validate it, and retry if it doesn't meet requirements.

## The Pattern

1. Generate output with an LLM
2. Validate the output (schema, business rules, etc.)
3. If invalid, send the errors back to the LLM and ask it to fix the output
4. Repeat until valid or max retries reached

## Example: JSON with Retry

```php
<?php

use function HelgeSverre\Synapse\{useLlm, createChatPrompt, createParser, createLlmExecutor};

$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]);

$prompt = createChatPrompt()
    ->addSystemMessage(
        'Generate a user profile as JSON with fields: ' .
        'name (string, required), email (string, valid email), ' .
        'age (number, 18-120), bio (string, max 200 chars).'
    )
    ->addUserMessage('Create a profile for: {{description}}', parseTemplate: true);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('json'),
    'model' => 'gpt-4o-mini',
]);

function validateProfile(array $data): array
{
    $errors = [];
    if (empty($data['name'])) {
        $errors[] = 'name is required';
    }
    if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'email must be a valid email address';
    }
    if (($data['age'] ?? 0) < 18 || ($data['age'] ?? 0) > 120) {
        $errors[] = 'age must be between 18 and 120';
    }
    if (strlen($data['bio'] ?? '') > 200) {
        $errors[] = 'bio must be 200 characters or less';
    }
    return $errors;
}

// Generate with retry
$maxRetries = 3;
$input = ['description' => 'A PHP developer named Alice who loves open source'];

for ($i = 0; $i < $maxRetries; $i++) {
    $result = $executor->execute($input);
    $data = $result->getValue();
    $errors = validateProfile($data);

    if (empty($errors)) {
        echo "Valid profile generated!\n";
        print_r($data);
        break;
    }

    // Add error feedback for next attempt
    $prompt->addAssistantMessage(json_encode($data));
    $prompt->addUserMessage(
        'The previous output had these errors: ' . implode(', ', $errors) .
        '. Please fix and try again.',
    );
}
```

## Tips

- Keep validation logic outside the LLM (deterministic checks)
- Include specific error messages so the LLM knows what to fix
- Set a reasonable `maxRetries` to avoid infinite loops
- Consider using `temperature: 0` for more deterministic output
