# Executors

Executors are the core building blocks that orchestrate the LLM pipeline. Each executor type handles a different workflow.

## The Pipeline

Every executor follows the same base pattern: take input, run a handler, return a result. The `BaseExecutor` class provides lifecycle hooks, state management, and error handling.

```php
$result = $executor->run(['question' => 'What is PHP?']);
$result->getValue();    // The parsed output
$result->state;         // Updated ConversationState
$result->response;      // Raw GenerationResponse
$result->metadata;      // Additional metadata
```

For non-streaming executors, `run()` is the preferred API (it aliases `execute()`). You can also pass prior messages explicitly:

```php
$result = $executor->run(['question' => 'What is PHP?'], $historyMessages);
```

## Executor Types

| Type                                                                              | Description                                       |
| --------------------------------------------------------------------------------- | ------------------------------------------------- |
| [LlmExecutor](/executors/llm-executor)                                            | Standard LLM pipeline: prompt → provider → parser |
| [LlmExecutorWithFunctions](/executors/llm-executor-with-functions)                | LLM with automatic tool calling loop              |
| [StreamingLlmExecutor](/executors/streaming-executor)                             | Real-time token streaming                         |
| [StreamingLlmExecutorWithFunctions](/executors/streaming-executor-with-functions) | Streaming with tool calling                       |
| [CoreExecutor](/executors/core-executor)                                          | Wrap a plain PHP function                         |
| [CallableExecutor](/executors/callable-executor)                                  | Define a tool for LLM function calling            |
| [ToolRegistry](/executors/tool-registry)                                          | Tool registry for multiple tools                  |

## Unified Factory

`createExecutor()` is the canonical constructor. It selects executor type from `stream` + `tools` options:

```php
$executor = createExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('string'),
    'stream' => false, // set true for streaming executor variants
    'tools' => null,   // set ToolRegistry to enable tool-calling variants
]);
```

## Shared Features

All executors that extend `BaseExecutor` share these methods:

### Hooks

```php
use HelgeSverre\Synapse\Hooks\Events\{OnSuccess, OnError};

$executor
    ->on(OnSuccess::class, fn($e) => echo "Done in {$e->durationMs}ms")
    ->on(OnError::class, fn($e) => echo "Error: {$e->error->getMessage()}");

// Remove a listener
$executor->off(OnSuccess::class, $listener);
```

### Additional Methods

```php
$executor->getMetadata();                      // ExecutorMetadata
$executor->withHooks($hookDispatcher);         // static — clone with different hooks
$executor->getHooks();                         // HookDispatcherInterface
```

### State

```php
use HelgeSverre\Synapse\State\ConversationState;

// Get current state
$state = $executor->getState();

// Create executor with different state (immutable clone)
$newExecutor = $executor->withState(new ConversationState());
```

## ExecutionResult

Every `run()` call returns an `ExecutionResult`:

```php
$result = $executor->run(['question' => 'Hello']);

$result->getValue();   // mixed — the parsed value (string, array, bool, etc.)
$result->state;        // ConversationState — updated with the assistant's response
$result->response;     // GenerationResponse — raw provider response with usage info
$result->metadata;     // array — additional metadata
```
