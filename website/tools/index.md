# Tool Calling

Tool calling (also called function calling) lets the LLM request execution of PHP functions during a conversation. The LLM decides which tool to use, provides arguments, and receives the result.

## How It Works

1. You define tools with names, descriptions, parameters, and handlers
2. The LLM receives the tool definitions alongside the prompt
3. If the LLM decides a tool is needed, it responds with a tool call
4. Synapse executes the tool and sends the result back to the LLM
5. The LLM uses the result to generate its final response

## Quick Example

```php
use function HelgeSverre\Synapse\{
    useLlm, createChatPrompt, createParser,
    createLlmExecutorWithFunctions, useExecutors,
};

$tools = useExecutors([
    [
        'name' => 'get_weather',
        'description' => 'Get the current weather for a city',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'city' => ['type' => 'string', 'description' => 'City name'],
            ],
            'required' => ['city'],
        ],
        'handler' => fn($args) => ['temp' => 22, 'condition' => 'sunny'],
    ],
]);

$executor = createLlmExecutorWithFunctions([
    'llm' => useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]),
    'prompt' => createChatPrompt()
        ->addSystemMessage('You are a weather assistant.')
        ->addUserMessage('{{question}}', parseTemplate: true),
    'parser' => createParser('string'),
    'tools' => $tools,
]);

$result = $executor->execute(['question' => 'What is the weather in Oslo?']);
```

## Related Pages

- [Defining Tools](/tools/defining-tools) — Tool configuration and parameters
- [Multi-Turn Loops](/tools/multi-turn) — How the tool calling loop works
- [Streaming with Tools](/tools/streaming-tools) — Real-time streaming with tools
