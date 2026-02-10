# Defining Tools

Tools are defined as arrays and wrapped by `useExecutors()`.

## Tool Config

```php
$tools = useExecutors([
    [
        'name' => 'tool_name',         // Required: unique identifier
        'description' => 'What it does', // Required: helps LLM decide when to use it
        'parameters' => [...],          // JSON Schema for arguments
        'handler' => fn($args) => ..., // Function to execute
        'attributes' => [],            // Optional metadata
        'visibilityHandler' => null,   // Optional: control availability
        'validateInput' => null,       // Optional: validate arguments
    ],
]);
```

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
            'description' => 'Maximum number of results',
            'default' => 10,
        ],
        'filters' => [
            'type' => 'object',
            'properties' => [
                'category' => ['type' => 'string'],
                'min_price' => ['type' => 'number'],
            ],
        ],
    ],
    'required' => ['query'],
],
```

## Handler Function

The handler receives parsed arguments and an optional `ConversationState`:

```php
'handler' => function (array $args, ConversationState $state): mixed {
    return [
        'results' => searchDatabase($args['query'], $args['limit'] ?? 10),
    ];
},
```

Return values are JSON-encoded and sent to the LLM.

## Visibility Handler

Control when a tool is available:

```php
'visibilityHandler' => function (array $input, ConversationState $state): bool {
    // Only show this tool for admin users
    return $state->getAttribute('role') === 'admin';
},
```

Hidden tools are not included in the tool definitions sent to the LLM.

## Input Validation

Validate arguments before the handler runs:

```php
'validateInput' => function (array $args): array {
    $errors = [];
    if (empty($args['email'])) {
        $errors[] = 'Email is required';
    }
    if (!filter_var($args['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    return ['valid' => empty($errors), 'errors' => $errors];
},
```

## Multiple Tools Example

```php
$tools = useExecutors([
    [
        'name' => 'search_web',
        'description' => 'Search the web for information',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
            ],
            'required' => ['query'],
        ],
        'handler' => fn($args) => webSearch($args['query']),
    ],
    [
        'name' => 'read_file',
        'description' => 'Read a file from disk',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'File path'],
            ],
            'required' => ['path'],
        ],
        'handler' => fn($args) => file_get_contents($args['path']),
    ],
    [
        'name' => 'run_code',
        'description' => 'Execute a PHP expression',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'code' => ['type' => 'string'],
            ],
            'required' => ['code'],
        ],
        'handler' => fn($args) => eval("return {$args['code']};"),
    ],
]);
```
