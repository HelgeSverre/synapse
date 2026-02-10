# JsonParser

Extracts JSON from LLM responses and decodes it into a PHP array. Handles responses that wrap JSON in markdown code blocks.

## Factory

```php
$parser = createParser('json');

$parser = createParser('json', [
    'schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'number'],
        ],
    ],
    'validateSchema' => false,
    'validator' => null,
]);
```

## Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `schema` | `array\|null` | `null` | JSON Schema for validation |
| `validateSchema` | `bool` | `false` | Enable schema validation |
| `validator` | `JsonSchemaValidatorInterface\|null` | `null` | Custom schema validator |

## Behavior

1. Extracts JSON from the response text (strips markdown code block wrappers if present)
2. Decodes the JSON into a PHP array
3. Optionally validates against a JSON Schema

## Examples

### Basic JSON parsing

```php
$prompt = createChatPrompt()
    ->addSystemMessage('Always respond with valid JSON.')
    ->addUserMessage('Extract name and age from: "{{text}}"', parseTemplate: true);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('json'),
]);

$result = $executor->execute(['text' => 'John is 34 years old']);
$data = $result->getValue();
// ['name' => 'John', 'age' => 34]
```

### With Schema

```php
$parser = createParser('json', [
    'schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'number'],
            'email' => ['type' => 'string'],
        ],
        'required' => ['name', 'age'],
    ],
]);
```

### With Custom Validator

The `validator` must implement `JsonSchemaValidatorInterface`:

```php
use HelgeSverre\Synapse\Parser\JsonSchema\JsonSchemaValidatorInterface;
use HelgeSverre\Synapse\Parser\JsonSchema\ValidationResult;

$parser = createParser('json', [
    'validator' => new class implements JsonSchemaValidatorInterface {
        public function validate(array $data, array $schema): ValidationResult {
            $valid = isset($data['name']) && strlen($data['name']) > 0;
            return new ValidationResult($valid, $valid ? [] : ['name is required']);
        }
    },
]);
```
