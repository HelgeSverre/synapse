# Streaming

Streaming lets you receive LLM responses token-by-token as they are generated, instead of waiting for the full response.

## When to Use Streaming

- Chat UIs with real-time output
- Long-running generations where you want progress feedback
- Applications where perceived latency matters

## Requirements

Streaming requires `GuzzleStreamTransport`:

```php
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use GuzzleHttp\Client;

$transport = new GuzzleStreamTransport(new Client(['timeout' => 60]));
$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
    'transport' => $transport,
]);
```

## Quick Example

```php
use HelgeSverre\Synapse\Executor\StreamingLlmExecutor;
use HelgeSverre\Synapse\Streaming\TextDelta;

$executor = new StreamingLlmExecutor($llm, $prompt, 'gpt-4o-mini');

foreach ($executor->stream(['question' => 'Write a poem']) as $event) {
    if ($event instanceof TextDelta) {
        echo $event->text; // Print each token
    }
}
```

## Streaming Executors

| Executor | Description |
|----------|-------------|
| [StreamingLlmExecutor](/executors/streaming-executor) | Stream text responses |
| [StreamingLlmExecutorWithFunctions](/executors/streaming-executor-with-functions) | Stream with tool calling |

See [Stream Events](/streaming/events) for all event types and [Stream Transport](/streaming/transport) for transport setup.
