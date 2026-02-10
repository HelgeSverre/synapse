# ListParser

Splits the LLM response by newlines into a PHP array of strings.

## Factory

```php
$parser = createParser('list');
$parser = createParser('array');
```

## Example

```php
$prompt = createChatPrompt()
    ->addSystemMessage('List items one per line, no numbering or bullets.')
    ->addUserMessage('List 5 programming languages', parseTemplate: false);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('list'),
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute([]);
$result->getValue();
// ['Python', 'JavaScript', 'PHP', 'Go', 'Rust']
```

## Behavior

The parser splits the response text by newlines and filters out empty lines. Each line becomes an element in the resulting array.

## Use Cases

- Generating lists of items
- Extracting multiple entities
- Brainstorming (list ideas, suggestions)
- Tag extraction
