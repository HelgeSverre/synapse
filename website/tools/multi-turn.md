# Multi-Turn Tool Loops

`LlmExecutorWithFunctions` automatically handles multi-turn tool calling. The LLM can call tools multiple times in sequence before providing a final answer.

## How the Loop Works

```
1. Send prompt + tool definitions to LLM
2. LLM responds with tool call(s)
3. Execute each tool, collect results
4. Send results back to LLM
5. If LLM responds with more tool calls → go to step 3
6. If LLM responds with text → parse and return
```

## maxIterations

Controls how many rounds of tool calling are allowed before throwing:

```php
$executor = createLlmExecutorWithFunctions([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('string'),
    'tools' => $tools,
    'maxIterations' => 10, // Default: 10
]);
```

If the LLM keeps calling tools beyond `maxIterations`, a `RuntimeException` is thrown.

## Example: Multi-Step Research

The LLM might call multiple tools in sequence to gather information:

```php
$tools = useExecutors([
    [
        'name' => 'search',
        'description' => 'Search for information',
        'parameters' => [
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string']],
            'required' => ['query'],
        ],
        'handler' => fn($args) => searchDatabase($args['query']),
    ],
    [
        'name' => 'get_details',
        'description' => 'Get details about an item by ID',
        'parameters' => [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'string']],
            'required' => ['id'],
        ],
        'handler' => fn($args) => getDetails($args['id']),
    ],
]);

// The LLM might:
// 1. Call search("PHP frameworks") → gets list with IDs
// 2. Call get_details("laravel") → gets Laravel details
// 3. Call get_details("symfony") → gets Symfony details
// 4. Return final comparison text
```

## Tracking Tool Calls

Use the `OnToolCall` hook to monitor what tools are being called:

```php
use HelgeSverre\Synapse\Hooks\Events\OnToolCall;

$executor->on(OnToolCall::class, function ($event) {
    echo "[Tool] {$event->toolCall->name}(" .
         json_encode($event->toolCall->arguments) . ")\n";
});
```
