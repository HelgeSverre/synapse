# CallableExecutor

Defines a tool/function that the LLM can call. Used with [LlmExecutorWithFunctions](/executors/llm-executor-with-functions).

## Usage

```php
use function HelgeSverre\Synapse\createCallableExecutor;

$tool = createCallableExecutor([
    'name' => 'get_weather',
    'description' => 'Get the current weather for a location',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'location' => [
                'type' => 'string',
                'description' => 'City name',
            ],
        ],
        'required' => ['location'],
    ],
    'handler' => fn($args) => [
        'temp' => 22,
        'condition' => 'sunny',
        'location' => $args['location'],
    ],
]);
```

## Config Options

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `name` | `string` | Yes | Tool name (used by the LLM to call it) |
| `description` | `string` | Yes | What the tool does (helps the LLM decide when to use it) |
| `handler` | `callable` | Yes | Function to run when the tool is called |
| `parameters` | `array` | No | JSON Schema for the tool's parameters |
| `attributes` | `array` | No | Extra metadata |
| `visibilityHandler` | `callable` | No | Controls when this tool is available |
| `validateInput` | `callable` | No | Validates arguments before execution |

## Parameters Schema

Parameters use [JSON Schema](https://json-schema.org/) format:

```php
'parameters' => [
    'type' => 'object',
    'properties' => [
        'query' => [
            'type' => 'string',
            'description' => 'Search query',
        ],
        'limit' => [
            'type' => 'integer',
            'description' => 'Max results',
            'default' => 10,
        ],
    ],
    'required' => ['query'],
],
```

## Visibility Handler

Control when a tool is available based on input or state:

```php
$tool = createCallableExecutor([
    'name' => 'admin_action',
    'description' => 'Perform an admin action',
    'handler' => fn($args) => 'done',
    'visibilityHandler' => fn($input, $state) => $state->getAttribute('role') === 'admin',
]);
```

## Input Validation

Validate arguments before the handler runs:

```php
$tool = createCallableExecutor([
    'name' => 'send_email',
    'description' => 'Send an email',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'to' => ['type' => 'string'],
            'subject' => ['type' => 'string'],
        ],
        'required' => ['to', 'subject'],
    ],
    'handler' => fn($args) => sendEmail($args['to'], $args['subject']),
    'validateInput' => function ($args) {
        $errors = [];
        if (!filter_var($args['to'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }
        return ['valid' => empty($errors), 'errors' => $errors];
    },
]);
```

## Handler Signature

The handler receives the arguments array and optionally the conversation state:

```php
'handler' => function (array $args, ConversationState $state): mixed {
    // $args contains the arguments from the LLM
    // $state contains the current conversation state
    return ['result' => 'something'];
},
```

The return value is JSON-encoded and sent back to the LLM as a tool result.

## ToolResult

The `execute()` method returns a `ToolResult`:

```php
$result = $tool->execute(['location' => 'Oslo']);

$result->result;      // mixed — the return value
$result->success;     // bool — whether execution succeeded
$result->errors;      // array — error messages if failed
$result->attributes;  // array — additional metadata
$result->toJson();    // string — JSON representation
```

## Additional Methods

```php
$tool->getName();                        // string — tool name
$tool->getDescription();                 // string — tool description
$tool->getParameters();                  // array — JSON Schema parameters
$tool->getAttributes();                  // array — custom attributes
$tool->validateInput($args);             // array — ['valid' => bool, 'errors' => [...]]
$tool->isVisible($args, $state);         // bool — check visibility
$tool->toToolDefinition();               // ToolDefinition — convert to provider format
```
