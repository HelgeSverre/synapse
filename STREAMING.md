# Streaming Support Investigation

## Current State

The library declares `supportsStreaming: true` in provider capabilities but **does not implement streaming**. All providers use synchronous request/response patterns.

### Blocking Points

| Layer | Current Implementation | Streaming Requirement |
|-------|----------------------|----------------------|
| Transport | `Psr18Transport::post()` returns `array` after full response | Must return `Generator<string>` or `iterable` of chunks |
| Provider | `generate()` returns `GenerationResponse` | Must yield `StreamChunk` objects incrementally |
| Executor | `handler()` returns `ExecutionResult` | Must yield partial results or emit events |
| Parser | Expects complete `GenerationResponse` | Must handle partial/incremental text |

## API Formats by Provider

### OpenAI / Mistral / XAI (SSE)

Request: `"stream": true`

Response format (Server-Sent Events):
```
data: {"id":"chatcmpl-...","choices":[{"delta":{"content":"Hello"}}]}

data: {"id":"chatcmpl-...","choices":[{"delta":{"content":" world"}}]}

data: [DONE]
```

Tool calls stream as:
```
data: {"choices":[{"delta":{"tool_calls":[{"index":0,"id":"call_abc","function":{"name":"get_weather","arguments":""}}]}}]}
data: {"choices":[{"delta":{"tool_calls":[{"index":0,"function":{"arguments":"{\"city\":"}}]}}]}
data: {"choices":[{"delta":{"tool_calls":[{"index":0,"function":{"arguments":"\"Oslo\"}"}}]}}]}
```

### Anthropic (SSE)

Request: `"stream": true`

Response format:
```
event: message_start
data: {"type":"message_start","message":{"id":"msg_...","model":"claude-3"}}

event: content_block_start
data: {"type":"content_block_start","index":0,"content_block":{"type":"text","text":""}}

event: content_block_delta
data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"Hello"}}

event: content_block_stop
data: {"type":"content_block_stop","index":0}

event: message_stop
data: {"type":"message_stop"}
```

Tool use streams as `content_block_start` with `type: "tool_use"`, then `input_json_delta` events.

### Google Gemini

Request: Uses `streamGenerateContent` endpoint instead of `generateContent`

Response format (newline-delimited JSON):
```json
{"candidates":[{"content":{"parts":[{"text":"Hello"}]}}]}
{"candidates":[{"content":{"parts":[{"text":" world"}]}}]}
{"candidates":[{"content":{"parts":[{"text":"!"}]},"finishReason":"STOP"}],"usageMetadata":{...}}
```

### AWS Bedrock

Uses `InvokeModelWithResponseStream` API. Response is binary event stream (AWS-specific framing).

## Proposed Architecture

### 1. New Interfaces

```php
interface StreamChunk
{
    public function getText(): ?string;
    public function getToolCallDelta(): ?ToolCallDelta;
    public function isComplete(): bool;
    public function getUsage(): ?UsageInfo;
}

interface StreamableProviderInterface extends LlmProviderInterface
{
    /** @return Generator<StreamChunk> */
    public function stream(GenerationRequest $request): Generator;
}

interface StreamTransportInterface extends TransportInterface
{
    /** @return Generator<string> */
    public function streamPost(string $url, array $headers, array $body): Generator;
}
```

### 2. Transport Layer Changes

```php
final class Psr18Transport implements TransportInterface, StreamTransportInterface
{
    /** @return Generator<string> */
    public function streamPost(string $url, array $headers, array $body): Generator
    {
        $body['stream'] = true;
        $request = $this->buildRequest($url, $headers, $body);
        
        $response = $this->client->sendRequest($request);
        $stream = $response->getBody();
        
        while (!$stream->eof()) {
            $line = $this->readLine($stream);
            if ($line !== '') {
                yield $line;
            }
        }
    }
    
    private function readLine(StreamInterface $stream): string
    {
        $buffer = '';
        while (!$stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") break;
            $buffer .= $char;
        }
        return $buffer;
    }
}
```

### 3. SSE Parser Utility

```php
final class SseParser
{
    /** @return Generator<array{event: ?string, data: string}> */
    public static function parse(Generator $lines): Generator
    {
        $event = null;
        $data = [];
        
        foreach ($lines as $line) {
            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data[] = trim(substr($line, 5));
            } elseif ($line === '') {
                if ($data !== []) {
                    yield ['event' => $event, 'data' => implode("\n", $data)];
                    $event = null;
                    $data = [];
                }
            }
        }
    }
}
```

### 4. Provider Stream Implementation (OpenAI example)

```php
final class OpenAIProvider implements LlmProviderInterface, StreamableProviderInterface
{
    /** @return Generator<StreamChunk> */
    public function stream(GenerationRequest $request): Generator
    {
        $body = $this->buildRequestBody($request);
        $body['stream'] = true;
        
        $lines = $this->transport->streamPost($this->baseUrl . '/chat/completions', $this->headers, $body);
        
        foreach (SseParser::parse($lines) as $event) {
            if ($event['data'] === '[DONE]') {
                return;
            }
            
            $chunk = json_decode($event['data'], true);
            yield $this->parseStreamChunk($chunk);
        }
    }
    
    private function parseStreamChunk(array $chunk): StreamChunk
    {
        $delta = $chunk['choices'][0]['delta'] ?? [];
        
        return new StreamChunk(
            text: $delta['content'] ?? null,
            toolCallDelta: $this->parseToolCallDelta($delta['tool_calls'] ?? null),
            isComplete: ($chunk['choices'][0]['finish_reason'] ?? null) !== null,
            usage: isset($chunk['usage']) ? $this->parseUsage($chunk['usage']) : null,
        );
    }
}
```

### 5. Executor Streaming

```php
final class StreamingExecutor
{
    /** @return Generator<StreamChunk> */
    public function stream(mixed $input): Generator
    {
        $rendered = $this->prompt->render($input);
        $messages = $this->buildMessages($rendered, $input);
        
        $request = new GenerationRequest(
            model: $this->model,
            messages: $messages,
            temperature: $this->temperature,
        );
        
        yield from $this->provider->stream($request);
    }
}
```

### 6. Consumer-Side Usage

```php
// Basic streaming
$executor = Factory::streamExecutor($provider, $prompt, 'gpt-4');

foreach ($executor->stream($input) as $chunk) {
    if ($chunk->getText() !== null) {
        echo $chunk->getText();
        flush();
    }
}

// With callback
$executor->stream($input, function (StreamChunk $chunk) {
    echo $chunk->getText();
});

// Collect full response
$response = $executor->streamAndCollect($input); // Returns GenerationResponse
```

## Tool Calling with Streaming

Tool calls are particularly complex because:

1. Tool call ID comes first, then function name, then arguments incrementally
2. Arguments are streamed as partial JSON strings that must be accumulated
3. Multiple tool calls can be in flight simultaneously (by index)

### Accumulator Pattern

```php
final class ToolCallAccumulator
{
    /** @var array<int, array{id: string, name: string, arguments: string}> */
    private array $calls = [];
    
    public function addDelta(ToolCallDelta $delta): void
    {
        $index = $delta->index;
        
        if (!isset($this->calls[$index])) {
            $this->calls[$index] = ['id' => '', 'name' => '', 'arguments' => ''];
        }
        
        if ($delta->id !== null) {
            $this->calls[$index]['id'] = $delta->id;
        }
        if ($delta->name !== null) {
            $this->calls[$index]['name'] = $delta->name;
        }
        if ($delta->arguments !== null) {
            $this->calls[$index]['arguments'] .= $delta->arguments;
        }
    }
    
    /** @return list<ToolCall> */
    public function getCompleteToolCalls(): array
    {
        return array_map(
            fn ($call) => new ToolCall(
                $call['id'],
                $call['name'],
                json_decode($call['arguments'], true) ?? [],
            ),
            array_values($this->calls),
        );
    }
}
```

## PSR Compatibility Concerns

- **PSR-18** (`ClientInterface`): Synchronous only, no streaming support
- **PSR-7** (`StreamInterface`): Supports streaming reads, but PSR-18 clients may buffer
- **Guzzle**: Supports streaming via `'stream' => true` option
- **Symfony HttpClient**: Native streaming support

### Recommendation

1. Keep `Psr18Transport` for non-streaming (wide compatibility)
2. Add `GuzzleStreamTransport` for streaming with Guzzle
3. Add `SymfonyStreamTransport` for streaming with Symfony HttpClient
4. Auto-detect available transport in Factory

## Implementation Priority

1. **Phase 1**: Transport layer streaming (`streamPost`)
2. **Phase 2**: SSE parser utility
3. **Phase 3**: OpenAI streaming (most common provider)
4. **Phase 4**: Anthropic streaming (different SSE format)
5. **Phase 5**: Other providers
6. **Phase 6**: Streaming executor with tool call support
7. **Phase 7**: ReactPHP/Amp async integration (optional)

## Open Questions (Resolved)

Based on research of existing PHP LLM libraries (openai-php/client, anthropic-sdk-php, symfony/ai), here are the recommended answers:

### 1. Should `stream()` be on the same interface or a separate `StreamableProviderInterface`?

**Answer: Separate `StreamableProviderInterface`**

```php
interface StreamableProviderInterface extends LlmProviderInterface
{
    /** @return Generator<StreamEvent> */
    public function stream(GenerationRequest $request, ?StreamContext $ctx = null): Generator;
}
```

**Rationale:**
- Existing `generate()` is stable; adding `stream()` forces all providers to implement immediately
- Streaming support varies by provider/transport (PSR-18 buffering, Bedrock binary framing)
- Matches industry patterns: openai-php uses `createStreamed()`, anthropic-sdk uses `createStream()`
- Pairs naturally with `getCapabilities()->supportsStreaming`

### 2. How to handle streaming + tool calls in executor loop?

**Answer: Stream one model "turn" at a time, re-stream after tool execution**

```php
while ($iterations++ < $max) {
    $acc = new ToolCallAccumulator();
    
    foreach ($provider->stream($request, $ctx) as $event) {
        if ($event instanceof TextDelta) {
            yield $event;  // User-visible tokens
        }
        if ($event instanceof ToolCallDelta) {
            $acc->add($event->delta);  // Accumulate internally
        }
    }
    
    $toolCalls = $acc->finalizeIfAny();
    if ($toolCalls === []) {
        break;  // No tool calls, done
    }
    
    // Execute tools, append results to messages
    foreach ($toolCalls as $toolCall) {
        $result = $this->tools->callFunction($toolCall->name, $toolCall->arguments);
        $messages[] = Message::tool($result, $toolCall->id, $toolCall->name);
    }
    // Loop continues with new streamed request
}
```

**Key insight from symfony/ai:** Accumulate tool call JSON fragments internally, only expose complete `ToolCall` objects. Don't expose partial/invalid JSON to consumers.

### 3. Should we support backpressure / cancellation?

**Answer: Yes, but lightweight**

**Backpressure:** Built-in via Generator pull-based iteration. Consumer controls pace.

**Cancellation:** Optional `StreamContext` with cancellation callback:

```php
final class StreamContext
{
    public function __construct(
        public readonly ?Closure $isCancelled = null,  // fn(): bool
        public readonly ?float $timeout = null,
    ) {}
}
```

Provider stream loops check `$ctx->isCancelled()` between events. Consumers can also just `break` out of the foreach.

For Laravel/web: tie into `connection_aborted()` check.

### 4. Integration with Laravel's streaming responses?

**Answer: Document adapter pattern, don't hard-depend on Laravel**

```php
// In Laravel controller
return response()->stream(function () use ($executor, $input) {
    foreach ($executor->stream($input) as $event) {
        if ($event instanceof TextDelta) {
            echo "data: " . json_encode(['text' => $event->text]) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();
        }
    }
}, 200, [
    'Content-Type' => 'text/event-stream',
    'Cache-Control' => 'no-cache',
    'X-Accel-Buffering' => 'no',  // Disable nginx buffering
]);
```

**Important notes to document:**
- Disable output buffering
- Handle client disconnect via `connection_aborted()` → `StreamContext`
- Nginx needs `X-Accel-Buffering: no` header

### 5. WebSocket transport for bidirectional streaming?

**Answer: Not in core (yet)**

**Rationale:**
- All major LLM providers use HTTP SSE streaming, not WebSockets
- Adds dependencies and runtime complexity
- Complicates transport abstraction significantly

**Recommended pattern:** Applications forward stream events over WebSockets themselves:
- Server iterates stream, broadcasts deltas to WS clients
- Client sends cancel/new-prompt over WS
- Server maps to `StreamContext` cancellation

Consider as optional adapter/bridge package later if demand emerges.

---

## Industry Patterns Research

### openai-php/client

**Key patterns:**
- `StreamResponse` class wraps `ResponseInterface`, implements `IteratorAggregate`
- Byte-by-byte SSE parsing via `readLine()` on `StreamInterface`
- Generic `StreamResponse<TResponse>` decouples SSE parsing from response typing
- Handles `[DONE]` termination, skips `ping`/`keepalive` events
- Guzzle: `['stream' => true]` option for true streaming

```php
// Their StreamResponse core loop
public function getIterator(): Generator
{
    while (!$this->response->getBody()->eof()) {
        $line = $this->readLine($this->response->getBody());
        
        if (str_starts_with($line, 'event:')) {
            $event = trim(substr($line, 6));
            $line = $this->readLine($this->response->getBody());
        }
        
        if (!str_starts_with($line, 'data:')) continue;
        
        $data = trim(substr($line, 5));
        if ($data === '[DONE]') break;
        
        yield $this->responseClass::from(json_decode($data, true));
    }
}
```

### anthropic-sdk-php

**Key patterns:**
- Layered decoding: HTTP → Lines → SSE events → JSON → Typed objects
- **Discriminator pattern**: Uses `type` field to route events to correct classes
- Union types for polymorphic events (`TextDelta|InputJSONDelta|ToolUseBlock`)
- Multi-line data support (SSE spec allows repeated `data:` lines)

```php
// Their event routing
public static function variants(): array
{
    return [
        'message_start' => RawMessageStartEvent::class,
        'content_block_delta' => RawContentBlockDeltaEvent::class,
        'content_block_stop' => RawContentBlockStopEvent::class,
        'message_stop' => RawMessageStopEvent::class,
    ];
}
```

### symfony/ai (formerly php-llm/llm-chain)

**Key patterns:**
- Options-based: `['stream' => true]` to same `invoke()` method
- **Listener pattern**: `onStart()`, `onChunk()`, `onComplete()` lifecycle
- `DeferredResult` for lazy evaluation (conversion delayed until iteration)
- Provider-specific `ResultConverter` classes normalize SSE formats
- Token usage extracted via listener, not in main stream

```php
// Their listener interface
interface ListenerInterface {
    public function onStart(StartEvent $event): void;
    public function onChunk(ChunkEvent $event): void;
    public function onComplete(CompleteEvent $event): void;
}

// Tool call accumulation in Anthropic converter
if ('content_block_delta' === $type && 'input_json_delta' === $data['delta']['type']) {
    $currentToolCallJson .= $data['delta']['partial_json'];
}
if ('content_block_stop' === $type && $currentToolCall !== null) {
    $toolCalls[] = new ToolCall(..., json_decode($currentToolCallJson));
}
```

---

## Refined Event Model

Based on research, recommend a minimal event set:

```php
// Base interface
interface StreamEvent {}

// Text content delta
final readonly class TextDelta implements StreamEvent
{
    public function __construct(public string $text) {}
}

// Tool call completed (not partial)
final readonly class ToolCallsReady implements StreamEvent
{
    /** @param list<ToolCall> $toolCalls */
    public function __construct(public array $toolCalls) {}
}

// Stream completed with final response
final readonly class StreamCompleted implements StreamEvent
{
    public function __construct(
        public GenerationResponse $response,
        public ?UsageInfo $usage = null,
    ) {}
}

// Optional: raw chunk for advanced use
final readonly class RawChunk implements StreamEvent
{
    public function __construct(public array $data) {}
}
```

**Design decision:** Don't expose partial tool call JSON to consumers by default. Accumulate internally and emit `ToolCallsReady` when complete.

---

## Risks and Guardrails

| Risk | Guardrail |
|------|-----------|
| PSR-18 clients may buffer entire response | Require known streaming-capable transport; fail fast if unavailable |
| Tool call JSON invalid until complete | Accumulate internally; JSON-decode only at completion; surface structured error with raw string on failure |
| Laravel/nginx buffering kills streaming | Document required headers (`X-Accel-Buffering: no`) and flush patterns |
| Provider SSE format differences | Provider-specific parsers normalize to common event types |

---

## References

### Provider Documentation
- [OpenAI Streaming](https://platform.openai.com/docs/api-reference/chat/create#chat-create-stream)
- [Anthropic Streaming](https://docs.anthropic.com/en/api/streaming)
- [Google Gemini Streaming](https://ai.google.dev/gemini-api/docs/text-generation#generate-a-text-stream)
- [AWS Bedrock Streaming](https://docs.aws.amazon.com/bedrock/latest/APIReference/API_runtime_InvokeModelWithResponseStream.html)

### PHP Library Implementations
- [openai-php/client](https://github.com/openai-php/client) - StreamResponse, SSE parsing, Guzzle integration
- [anthropic-sdk-php](https://github.com/anthropics/anthropic-sdk-php) - Discriminator unions, typed events
- [symfony/ai](https://github.com/symfony/ai) (formerly php-llm/llm-chain) - Listener pattern, DeferredResult, multi-provider
