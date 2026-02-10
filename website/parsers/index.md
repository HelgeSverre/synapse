# Parsers

Parsers extract structured data from LLM responses. Instead of working with raw text, parsers give you typed PHP values — arrays, booleans, numbers, lists, and more.

## The createParser() Factory

```php
use function HelgeSverre\Synapse\createParser;

$parser = createParser('json', ['schema' => [...]]);
```

## Available Parsers

| Factory String | Parser Class | Returns |
|---------------|-------------|---------|
| `'string'` | [StringParser](/parsers/string) | `string` |
| `'json'` | [JsonParser](/parsers/json) | `array` |
| `'boolean'`, `'bool'` | [BooleanParser](/parsers/boolean) | `bool` |
| `'number'`, `'int'`, `'float'` | [NumberParser](/parsers/number) | `int\|float` |
| `'list'`, `'array'` | [ListParser](/parsers/list) | `string[]` |
| `'enum'` | [EnumParser](/parsers/enum) | `?string` |
| `'code'`, `'codeblock'`, `'markdownCodeBlock'` | [MarkdownCodeBlockParser](/parsers/code-block) | `string` |
| `'codeblocks'`, `'markdownCodeBlocks'` | [MarkdownCodeBlocksParser](/parsers/code-block) | `array[]` |
| `'keyvalue'`, `'key-value'` | [ListToKeyValueParser](/parsers/key-value) | `array` |
| `'listjson'`, `'list-json'` | [ListToJsonParser](/parsers/key-value) | `array` |
| `'template'`, `'replace'` | [ReplaceStringTemplateParser](/parsers/custom) | `string` |
| `'function'`, `'tool'` | [LlmFunctionParser](/parsers/custom) | `mixed` |
| `'custom'` | [CustomParser](/parsers/custom) | `mixed` |

## How Parsers Work

Parsers implement `ParserInterface`:

```php
interface ParserInterface
{
    public function parse(GenerationResponse $response): mixed;
}
```

They receive the raw `GenerationResponse` from the provider and return a parsed value. The executor wraps this value in an `ExecutionResult`.

## Using with Executors

```php
$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('json'),  // Parse response as JSON
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute(['text' => 'John, age 34']);
$data = $result->getValue(); // PHP array from JSON
```

If no parser is specified, `StringParser` is used by default.
