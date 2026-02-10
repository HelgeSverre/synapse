# LlmExecutorWithFunctions

An executor that supports tool/function calling. When the LLM responds with tool calls, the executor automatically runs the tools and feeds results back to the LLM in a loop.

## Usage

```php
use function HelgeSverre\Synapse\{
    useLlm, createChatPrompt, createParser,
    createLlmExecutorWithFunctions, useExecutors,
};

$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]);

$tools = useExecutors([
    [
        'name' => 'get_weather',
        'description' => 'Get weather for a location',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'location' => ['type' => 'string', 'description' => 'City name'],
            ],
            'required' => ['location'],
        ],
        'handler' => fn($args) => ['temp' => 22, 'condition' => 'sunny'],
    ],
]);

$executor = createLlmExecutorWithFunctions([
    'llm' => $llm,
    'prompt' => createChatPrompt()
        ->addSystemMessage('You are a weather assistant.')
        ->addUserMessage('{{question}}', parseTemplate: true),
    'parser' => createParser('string'),
    'tools' => $tools,
    'maxIterations' => 10,
]);

$result = $executor->execute(['question' => 'What is the weather in Oslo?']);
echo $result->getValue();
// "The weather in Oslo is 22°C and sunny."
```

## The Tool Calling Loop

When you call `execute()`:

1. Prompt is rendered and sent to the LLM with tool definitions
2. If the LLM responds with **tool calls**:
   - Each tool is executed with the provided arguments
   - Tool results are added as messages
   - The LLM is called again with the updated messages
   - This repeats until the LLM responds with text or `maxIterations` is reached
3. If the LLM responds with **text**, the parser extracts the result

```
User message
    ↓
LLM (with tools)
    ↓
Tool call? ──yes──→ Execute tools → Add results → Loop back to LLM
    │
    no
    ↓
Parse response → Return result
```

## Factory Options

Same as [LlmExecutor](/executors/llm-executor), plus:

| Option | Type | Required | Default | Description |
|--------|------|----------|---------|-------------|
| `tools` | `UseExecutors` or `array` | Yes | — | Tool registry or array of tool configs |
| `maxIterations` | `int` | No | `10` | Max tool calling rounds before throwing |

## Multiple Tools

```php
$tools = useExecutors([
    [
        'name' => 'search',
        'description' => 'Search the web',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
            ],
            'required' => ['query'],
        ],
        'handler' => fn($args) => "Results for: {$args['query']}",
    ],
    [
        'name' => 'calculate',
        'description' => 'Calculate a math expression',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'expression' => ['type' => 'string'],
            ],
            'required' => ['expression'],
        ],
        'handler' => fn($args) => ['result' => eval("return {$args['expression']};")],
    ],
]);
```

## Hooks for Tool Calls

```php
use HelgeSverre\Synapse\Hooks\Events\OnToolCall;

$executor->on(OnToolCall::class, function ($event) {
    echo "Tool called: {$event->toolCall->name}\n";
    echo "Arguments: " . json_encode($event->toolCall->arguments) . "\n";
});
```
