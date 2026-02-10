# UseExecutors

A registry that manages multiple [CallableExecutors](/executors/callable-executor) (tools). Used as the `tools` parameter in [LlmExecutorWithFunctions](/executors/llm-executor-with-functions).

## Usage

```php
use function HelgeSverre\Synapse\useExecutors;

$tools = useExecutors([
    [
        'name' => 'get_weather',
        'description' => 'Get weather for a location',
        'parameters' => [
            'type' => 'object',
            'properties' => ['location' => ['type' => 'string']],
            'required' => ['location'],
        ],
        'handler' => fn($args) => ['temp' => 22],
    ],
    [
        'name' => 'search',
        'description' => 'Search the web',
        'parameters' => [
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string']],
            'required' => ['query'],
        ],
        'handler' => fn($args) => "Results for: {$args['query']}",
    ],
]);

// Use with an executor
$executor = createLlmExecutorWithFunctions([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('string'),
    'tools' => $tools,
]);
```

## Methods

### getToolDefinitions()

Returns `ToolDefinition[]` for sending to the LLM provider:

```php
$definitions = $tools->getToolDefinitions();
```

### callFunction()

Execute a tool by name:

```php
$result = $tools->callFunction('get_weather', ['location' => 'Oslo']);
```

### hasFunction() / getFunction()

```php
$tools->hasFunction('get_weather'); // true
$tool = $tools->getFunction('get_weather'); // CallableExecutor
```

### register()

Add tools after construction:

```php
$tools->register(createCallableExecutor([
    'name' => 'new_tool',
    'description' => 'A new tool',
    'handler' => fn($args) => 'result',
]));
```

### Visibility Filtering

Get only tools visible for the current context:

```php
$visible = $tools->getVisibleFunctions($input, $state);
$visibleDefs = $tools->getVisibleToolDefinitions($input, $state);
```
