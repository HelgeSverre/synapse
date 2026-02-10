# Code Block Parsers

Extract code from markdown code blocks in LLM responses.

## MarkdownCodeBlockParser

Extracts a single code block.

### Factory

```php
$parser = createParser('code');
$parser = createParser('codeblock');
$parser = createParser('code', [
    'language' => 'php',     // Filter by language
    'firstOnly' => true,     // Return only the first match (default)
]);
```

### Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `language` | `string\|null` | `null` | Filter by language tag |
| `firstOnly` | `bool` | `true` | Return only the first code block |

### Example

```php
$prompt = createChatPrompt()
    ->addSystemMessage('Generate PHP code. Wrap it in a code block.')
    ->addUserMessage('Write a function that adds two numbers', parseTemplate: false);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('code', ['language' => 'php']),
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute([]);
$block = $result->getValue();
// "function add(int $a, int $b): int { ... }"
```

## MarkdownCodeBlocksParser

Extracts multiple code blocks from the response.

### Factory

```php
$parser = createParser('codeblocks');
```

### Example

```php
$prompt = createChatPrompt()
    ->addSystemMessage('Generate both the PHP class and a PHPUnit test.')
    ->addUserMessage('Create a Calculator class', parseTemplate: false);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('codeblocks'),
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute([]);
$blocks = $result->getValue();
// [
//     ['language' => 'php', 'code' => 'class Calculator { ... }'],
//     ['language' => 'php', 'code' => 'class CalculatorTest { ... }'],
// ]
```
