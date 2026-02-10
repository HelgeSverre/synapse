# Streaming with Tools

`StreamingLlmExecutorWithFunctions` combines real-time streaming with tool calling.

## Usage

```php
use HelgeSverre\Synapse\Executor\StreamingLlmExecutorWithFunctions;
use HelgeSverre\Synapse\Streaming\{TextDelta, ToolCallDelta, ToolCallsReady, StreamCompleted};

$executor = new StreamingLlmExecutorWithFunctions(
    provider: $streamableProvider,
    prompt: $prompt,
    model: 'gpt-4o-mini',
    tools: $tools,
);

foreach ($executor->stream(['question' => 'Check the weather']) as $event) {
    match (true) {
        $event instanceof TextDelta => print($event->text),
        $event instanceof ToolCallDelta => null, // Partial tool call data
        $event instanceof ToolCallsReady => print("[Executing tools...]\n"),
        $event instanceof StreamCompleted => print("\n[Done]\n"),
        default => null,
    };
}
```

## Stream Events During Tool Calls

1. **ToolCallDelta** events arrive as the LLM streams tool call data (name, arguments)
2. Once all tool calls for a round are complete, **ToolCallsReady** fires
3. Tools are executed automatically
4. The LLM is called again and streams its next response
5. **TextDelta** events arrive with the final answer (or more ToolCallDelta for another round)

## ToolCallDelta

Contains partial data as the tool call is being built:

```php
if ($event instanceof ToolCallDelta) {
    echo "Building tool call: {$event->name}\n";
    echo "Arguments so far: {$event->arguments}\n";
}
```

## ToolCallsReady

Fires when all tool calls for the current round are assembled:

```php
if ($event instanceof ToolCallsReady) {
    foreach ($event->toolCalls as $call) {
        echo "Will execute: {$call->name}({$call->arguments})\n";
    }
}
```
