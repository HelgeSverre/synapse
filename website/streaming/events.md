# Stream Events

The `stream()` method yields `StreamEvent` objects. Handle different event types in a foreach loop.

## Event Types

### TextDelta

A chunk of text from the LLM:

```php
use HelgeSverre\Synapse\Streaming\TextDelta;

if ($event instanceof TextDelta) {
    echo $event->text; // "Hello"
}
```

### ToolCallDelta

Partial tool call data as it streams. These are typically accumulated internally by `StreamingLlmExecutorWithFunctions` and not consumed directly — use `ToolCallsReady` for complete tool calls.

```php
use HelgeSverre\Synapse\Streaming\ToolCallDelta;

if ($event instanceof ToolCallDelta) {
    echo $event->index;     // int — tool call index
    echo $event->id;        // ?string — tool call ID
    echo $event->name;      // ?string — tool name (may be partial)
    echo $event->arguments; // ?string — arguments (may be partial JSON)
}
```

### ToolCallsReady

All tool calls for the current round are complete:

```php
use HelgeSverre\Synapse\Streaming\ToolCallsReady;

if ($event instanceof ToolCallsReady) {
    foreach ($event->toolCalls as $toolCall) {
        echo "Execute: {$toolCall->name}\n";
    }
}
```

### StreamCompleted

The stream has finished:

```php
use HelgeSverre\Synapse\Streaming\StreamCompleted;

if ($event instanceof StreamCompleted) {
    echo $event->finishReason;        // 'stop', 'length', 'tool_calls'
    echo $event->usage?->getTotal();  // Total tokens
}
```

## Complete Handler

```php
foreach ($executor->stream($input) as $event) {
    match (true) {
        $event instanceof TextDelta => print($event->text),
        $event instanceof ToolCallDelta => null, // Handle if needed
        $event instanceof ToolCallsReady => print("\n[Executing tools...]\n"),
        $event instanceof StreamCompleted => print("\n[Done]\n"),
        default => null,
    };
}
```
