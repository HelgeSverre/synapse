# Key-Value Parsers

Parse key-value lists from LLM responses.

## ListToKeyValueParser

Parses a response with lines like `key: value` into a PHP associative array.

### Factory

```php
$parser = createParser('keyvalue');
$parser = createParser('key-value');
$parser = createParser('keyvalue', [
    'separator' => ':',       // default
    'trimValues' => true,     // default
]);
```

### Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `separator` | `string` | `':'` | Delimiter between key and value |
| `trimValues` | `bool` | `true` | Trim whitespace from values |

### Example

```php
$prompt = createChatPrompt()
    ->addSystemMessage('Extract metadata as key: value pairs, one per line.')
    ->addUserMessage('{{text}}', parseTemplate: true);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('keyvalue'),
]);

$result = $executor->execute(['text' => 'The book "1984" by George Orwell, published 1949']);
// ['title' => '1984', 'author' => 'George Orwell', 'year' => '1949']
```

## ListToJsonParser

Supports nested structures using indentation. Returns a PHP array (`array<string, mixed>`).

### Factory

```php
$parser = createParser('listjson');
$parser = createParser('list-json');
$parser = createParser('listjson', [
    'separator' => ':',
    'indentSpaces' => 2,
]);
```

### Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `separator` | `string` | `':'` | Delimiter between key and value |
| `indentSpaces` | `int` | `2` | Spaces per nesting level when parsing indented text |
