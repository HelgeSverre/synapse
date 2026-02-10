# Event Types

## Pipeline Events

### BeforePromptRender

Fires before the prompt template is rendered.

```php
use HelgeSverre\Synapse\Hooks\Events\BeforePromptRender;

$executor->on(BeforePromptRender::class, function ($event) {
    $event->prompt; // PromptInterface
    $event->values; // array — the input variables
});
```

### AfterPromptRender

Fires after the prompt is rendered into messages.

```php
use HelgeSverre\Synapse\Hooks\Events\AfterPromptRender;

$executor->on(AfterPromptRender::class, function ($event) {
    $event->rendered; // string|Message[] — rendered output
});
```

### BeforeProviderCall

Fires before the HTTP request to the LLM API.

```php
use HelgeSverre\Synapse\Hooks\Events\BeforeProviderCall;

$executor->on(BeforeProviderCall::class, function ($event) {
    $event->request; // GenerationRequest (model, messages, tools, etc.)
});
```

### AfterProviderCall

Fires after the LLM API responds.

```php
use HelgeSverre\Synapse\Hooks\Events\AfterProviderCall;

$executor->on(AfterProviderCall::class, function ($event) {
    $event->request;  // GenerationRequest
    $event->response; // GenerationResponse (text, usage, tool calls)
});
```

## Completion Events

### OnSuccess

Fires when execution completes successfully.

```php
use HelgeSverre\Synapse\Hooks\Events\OnSuccess;

$executor->on(OnSuccess::class, function ($event) {
    $event->result;     // ExecutionResult
    $event->durationMs; // float — execution time in ms
});
```

### OnError

Fires when execution throws an exception.

```php
use HelgeSverre\Synapse\Hooks\Events\OnError;

$executor->on(OnError::class, function ($event) {
    $event->error;   // Throwable
    $event->request; // ?GenerationRequest — the request that caused the error
});
```

### OnComplete

Fires after execution completes (success or failure).

```php
use HelgeSverre\Synapse\Hooks\Events\OnComplete;

$executor->on(OnComplete::class, function ($event) {
    $event->success;    // bool
    $event->durationMs; // float
    $event->error;      // ?Throwable
});
```

## Tool Events

### OnToolCall

Fires when a tool call is executed.

```php
use HelgeSverre\Synapse\Hooks\Events\OnToolCall;

$executor->on(OnToolCall::class, function ($event) {
    $event->toolCall; // ToolCall (name, arguments, id)
});
```

## Streaming Events

### OnStreamStart

```php
use HelgeSverre\Synapse\Hooks\Events\OnStreamStart;

$executor->on(OnStreamStart::class, function ($event) {
    $event->request; // GenerationRequest
});
```

### OnStreamChunk

Fires for each streamed event:

```php
use HelgeSverre\Synapse\Hooks\Events\OnStreamChunk;

$executor->on(OnStreamChunk::class, function ($event) {
    $event->event; // StreamEvent (TextDelta, ToolCallDelta, etc.)
});
```

### OnStreamEnd

```php
use HelgeSverre\Synapse\Hooks\Events\OnStreamEnd;

$executor->on(OnStreamEnd::class, function ($event) {
    $event->completed; // StreamCompleted
    $event->fullText;  // string — collected text
});
```

### OnStreamSuccess

```php
use HelgeSverre\Synapse\Hooks\Events\OnStreamSuccess;

$executor->on(OnStreamSuccess::class, function ($event) {
    $event->result;     // StreamingResult
    $event->durationMs; // float
});
```
