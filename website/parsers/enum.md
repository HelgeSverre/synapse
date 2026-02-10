# EnumParser

Matches the LLM response against a set of allowed values. Returns the matched value or `null` if no match is found.

## Factory

```php
$parser = createParser('enum', [
    'values' => ['low', 'medium', 'high'],
]);

$parser = createParser('enum', [
    'values' => ['low', 'medium', 'high'],
    'caseSensitive' => true,  // default: false
]);
```

## Options

| Option | Type | Required | Default | Description |
|--------|------|----------|---------|-------------|
| `values` | `array` | Yes | — | Allowed values to match against |
| `caseSensitive` | `bool` | No | `false` | Whether matching is case-sensitive |

## Example

```php
$prompt = createChatPrompt()
    ->addSystemMessage('Classify the urgency as: low, medium, or high. Respond with one word only.')
    ->addUserMessage('{{message}}', parseTemplate: true);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('enum', [
        'values' => ['low', 'medium', 'high'],
    ]),
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute(['message' => 'The server is on fire!']);
$result->getValue(); // "high"
```

## Use Cases

- Priority classification (low/medium/high)
- Sentiment analysis (positive/negative/neutral)
- Category assignment
- Intent detection with fixed categories
