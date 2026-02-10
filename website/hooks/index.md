# Hooks & Events

Hooks provide lifecycle events at each stage of the executor pipeline. Use them for logging, metrics, debugging, and custom behavior.

## Quick Example

```php
use HelgeSverre\Synapse\Hooks\Events\{BeforeProviderCall, AfterProviderCall, OnSuccess, OnError};

$executor
    ->on(BeforeProviderCall::class, fn($e) => echo "Calling {$e->request->model}...\n")
    ->on(AfterProviderCall::class, fn($e) => echo "Used {$e->response->usage->getTotal()} tokens\n")
    ->on(OnSuccess::class, fn($e) => echo "Completed in {$e->durationMs}ms\n")
    ->on(OnError::class, fn($e) => echo "Error: {$e->error->getMessage()}\n");
```

## How Hooks Work

1. Register listeners on an executor with `on()`
2. Events fire automatically during execution
3. Listeners receive the event object with relevant data

## Common Use Cases

- **Logging**: Log every LLM call with model, tokens, and duration
- **Metrics**: Track token usage and costs
- **Debugging**: Inspect rendered prompts and raw responses
- **Retry logic**: Re-execute on specific errors
- **Rate limiting**: Throttle based on token usage

## Event Lifecycle

```
execute() called
  → BeforePromptRender
  → AfterPromptRender
  → BeforeProviderCall
  → AfterProviderCall
  → OnSuccess (or OnError)
  → OnComplete
```

For streaming, additional events fire: `OnStreamStart`, `OnStreamChunk`, `OnStreamEnd`, `OnStreamSuccess`.

See [Event Types](/hooks/events) for all events and [HookDispatcher](/hooks/hook-dispatcher) for the dispatcher API.
