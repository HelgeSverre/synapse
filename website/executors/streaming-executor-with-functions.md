# StreamingLlmExecutorWithFunctions

Combines streaming with tool calling. Streams the LLM response in real-time and automatically handles tool calls in a loop.

## Usage

```php
use function HelgeSverre\Synapse\createStreamingLlmExecutorWithFunctions;
use HelgeSverre\Synapse\Streaming\{TextDelta, ToolCallsReady, StreamCompleted};

// Via factory
$executor = createStreamingLlmExecutorWithFunctions([
    'llm' => $streamableProvider,
    'prompt' => $prompt,
    'model' => 'gpt-4o-mini',
    'tools' => $tools,
    'maxIterations' => 10,
]);

foreach ($executor->stream(['question' => 'What is the weather in Oslo?']) as $event) {
    if ($event instanceof TextDelta) {
        echo $event->text;
    }
    if ($event instanceof ToolCallsReady) {
        // All tool calls for this round are complete
        echo "Executing " . count($event->toolCalls) . " tool(s)...\n";
    }
    if ($event instanceof StreamCompleted) {
        echo "\nDone.\n";
    }
}
```

## How It Works

1. The prompt and tool definitions are sent to the LLM
2. As the response streams, `TextDelta` events are yielded to the consumer; tool call deltas are accumulated internally
3. When tool calls are complete, a `ToolCallsReady` event is yielded
4. The executor automatically runs the tools and sends results back to the LLM
5. The LLM streams its next response (which may include more tool calls or final text)
6. This loops until the LLM responds with plain text or `maxIterations` is reached

## Stream Events

| Event | Description |
|-------|-------------|
| `TextDelta` | A chunk of text from the LLM |
| `ToolCallsReady` | Tool calls are complete, about to be executed |
| `StreamCompleted` | Stream finished |

::: info
`ToolCallDelta` events are accumulated internally and not yielded to consumers. Use `ToolCallsReady` for complete tool calls.
:::

## Collect Full Response

Use `streamAndCollect()` to consume the stream and get the final result:

```php
$result = $executor->streamAndCollect(['question' => 'What is the weather?']);

echo $result->text;          // Full accumulated text
echo $result->finishReason;  // 'stop', 'length', etc.
echo $result->usage;         // ?UsageInfo
```

See [Streaming with Tools](/tools/streaming-tools) for more details.
