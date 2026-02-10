# Stream Transport

## GuzzleStreamTransport

Required for streaming. Unlike `Psr18Transport` which buffers the full response, `GuzzleStreamTransport` reads the response body as a stream.

```php
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use GuzzleHttp\Client;

$transport = new GuzzleStreamTransport(new Client([
    'timeout' => 60,
]));
```

## StreamableProviderInterface

Providers that support streaming implement `StreamableProviderInterface`:

```php
interface StreamableProviderInterface extends LlmProviderInterface
{
    public function stream(GenerationRequest $request, ?StreamContext $ctx = null): Generator;
}
```

The `stream()` method returns a generator of `StreamEvent` objects.

## Setup Example

```php
$transport = new GuzzleStreamTransport(new Client(['timeout' => 60]));

$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
    'transport' => $transport,
]);

// Now use with StreamingLlmExecutor
$executor = new StreamingLlmExecutor($llm, $prompt, 'gpt-4o-mini');

foreach ($executor->stream(['question' => 'Hello']) as $event) {
    // Handle events
}
```

## StreamContext

Control cancellation and timeouts for streaming operations:

```php
use HelgeSverre\Synapse\Streaming\StreamContext;

// With cancellation callback and timeout
$startTime = time();
$ctx = new StreamContext(
    isCancelled: fn() => time() > $startTime + 30,
    timeout: 30.0,
);

$generator = $executor->stream($input, $ctx);
```

### Auto-cancel on connection drop

For web servers, cancel the stream when the HTTP connection drops:

```php
$ctx = StreamContext::withConnectionAbortCheck();
$generator = $executor->stream($input, $ctx);
```

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `isCancelled` | `?Closure` | Callback returning `true` to cancel |
| `timeout` | `?float` | Timeout in seconds |

Use `$ctx->shouldCancel()` to check if the stream should be cancelled.
