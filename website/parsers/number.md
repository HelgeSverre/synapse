# NumberParser

Extracts numeric values from LLM responses.

## Factory

```php
$parser = createParser('number');           // int or float
$parser = createParser('int');              // alias
$parser = createParser('float');            // alias
$parser = createParser('number', ['intOnly' => true]); // integers only
```

## Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `intOnly` | `bool` | `false` | Only return integers |

## Example

```php
$prompt = createChatPrompt()
    ->addSystemMessage('Respond with only a number.')
    ->addUserMessage('How many countries are in Europe?');

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('number'),
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute([]);
$result->getValue(); // 44
```

## Use Cases

- Scoring and rating tasks
- Counting and quantification
- Mathematical results
- Sentiment scores
