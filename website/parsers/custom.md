# Custom Parsers

For parsing logic that doesn't fit the built-in parsers.

## CustomParser

Provide your own parsing function:

### Factory

```php
$parser = createParser('custom', [
    'handler' => function (GenerationResponse $response): mixed {
        $text = $response->getText();
        // Your custom parsing logic
        return processText($text);
    },
]);
```

### Options

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `handler` | `callable` | Yes | Parsing function that receives `GenerationResponse` |

### Example

```php
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;

$parser = createParser('custom', [
    'handler' => function (GenerationResponse $response): array {
        $text = $response->getText();
        // Parse CSV-like response
        $rows = array_map('str_getcsv', explode("\n", trim($text)));
        $headers = array_shift($rows);
        return array_map(fn($row) => array_combine($headers, $row), $rows);
    },
]);
```

## ReplaceStringTemplateParser

Replaces placeholders in a template string with values from the LLM response.

### Factory

```php
$parser = createParser('template', [
    'replacements' => [
        '{{response}}' => fn($response) => $response->getText(),
    ],
    'strict' => false,
]);
```

## LlmFunctionParser

Used internally for parsing tool call results. Wraps another parser and delegates to it.

### Factory

```php
$parser = createParser('function', [
    'parser' => createParser('string'), // The inner parser
]);
$parser = createParser('tool', [
    'parser' => createParser('json'),
]);
```

## Building Your Own Parser

Implement `ParserInterface` directly:

```php
use HelgeSverre\Synapse\Parser\ParserInterface;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;

class MarkdownTableParser implements ParserInterface
{
    public function parse(GenerationResponse $response): array
    {
        $text = $response->getText();
        $lines = explode("\n", trim($text));

        // Skip header separator
        $headers = array_map('trim', explode('|', trim($lines[0], '|')));
        $rows = [];

        for ($i = 2; $i < count($lines); $i++) {
            $cells = array_map('trim', explode('|', trim($lines[$i], '|')));
            $rows[] = array_combine($headers, $cells);
        }

        return $rows;
    }
}

// Use directly
$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => new MarkdownTableParser(),
    'model' => 'gpt-4o-mini',
]);
```
