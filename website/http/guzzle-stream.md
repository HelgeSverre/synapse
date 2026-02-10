# Guzzle Stream Transport

Required for streaming responses. Reads the HTTP response body as a stream instead of buffering the entire response.

## Setup

```php
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use GuzzleHttp\Client;

$transport = new GuzzleStreamTransport(new Client([
    'timeout' => 60,
]));
```

## Usage with Streaming Executor

```php
$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
    'transport' => $transport,
]);

$executor = new StreamingLlmExecutor($llm, $prompt, 'gpt-4o-mini');

foreach ($executor->stream(['question' => 'Hello']) as $event) {
    if ($event instanceof TextDelta) {
        echo $event->text;
    }
}
```

## When to Use

Use `GuzzleStreamTransport` when:
- You need real-time token streaming
- You're building chat UIs with live output
- You're using `StreamingLlmExecutor` or `StreamingLlmExecutorWithFunctions`

Use `Psr18Transport` (the default) for standard buffered requests.

## Guzzle Client Options

Pass Guzzle options to customize behavior:

```php
$transport = new GuzzleStreamTransport(new Client([
    'timeout' => 120,        // Connection + read timeout
    'connect_timeout' => 10, // Connection timeout only
    'verify' => true,        // SSL verification
]));
```
