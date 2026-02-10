# StreamingLlmExecutor

Streams LLM responses in real-time as they are generated. Returns a generator of `StreamEvent` objects.

## Requirements

Streaming requires `GuzzleStreamTransport` instead of the default `Psr18Transport`:

```php
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use GuzzleHttp\Client;

$transport = new GuzzleStreamTransport(new Client(['timeout' => 60]));
```

## Usage

```php
use function HelgeSverre\Synapse\createStreamingLlmExecutor;
use HelgeSverre\Synapse\Streaming\TextDelta;
use HelgeSverre\Synapse\Streaming\StreamCompleted;

$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
    'transport' => $transport,
]);

$prompt = createChatPrompt()
    ->addSystemMessage('You are a helpful assistant.')
    ->addUserMessage('{{question}}', parseTemplate: true);

// Via factory (model comes from useLlm)
$executor = createStreamingLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
]);

// Or direct instantiation
// $executor = new StreamingLlmExecutor($llm, $prompt, 'gpt-4o-mini');

foreach ($executor->stream(['question' => 'Write a haiku about PHP']) as $event) {
    if ($event instanceof TextDelta) {
        echo $event->text; // Print each token as it arrives
    }
    if ($event instanceof StreamCompleted) {
        echo "\n\nTokens used: {$event->usage?->getTotal()}";
    }
}
```

## Constructor

```php
new StreamingLlmExecutor(
    provider: $streamableProvider,  // StreamableProviderInterface
    prompt: $prompt,                // PromptInterface
    model: 'gpt-4o-mini',          // string
    temperature: 0.7,              // ?float
    maxTokens: 1000,               // ?int
    name: 'my-streamer',           // ?string
    hooks: $hooks,                 // ?HookDispatcherInterface
    state: $state,                 // ?ConversationState
);
```

## Stream Events

The `stream()` method yields these event types:

| Event | Description |
|-------|-------------|
| `TextDelta` | A chunk of text (`$event->text`) |
| `StreamCompleted` | Stream finished (`$event->finishReason`, `$event->usage`) |

See [Stream Events](/streaming/events) for details.

## Collect Full Response

Use `streamAndCollect()` to consume the stream and get the final result:

```php
$result = $executor->streamAndCollect(['question' => 'Hello']);

echo $result->text;          // Full collected text
echo $result->finishReason;  // 'stop', 'length', etc.
echo $result->usage;         // UsageInfo
echo $result->state;         // Updated ConversationState
```

## Supported Hook Events

| Event | When |
|-------|------|
| `BeforePromptRender` | Before template rendering |
| `AfterPromptRender` | After template rendering |
| `OnStreamStart` | Stream connection opened |
| `OnStreamChunk` | Each streamed event |
| `OnStreamEnd` | Stream finished |
| `OnStreamSuccess` | Stream completed successfully |
| `OnError` | An exception occurred |
| `OnComplete` | After execution (success or failure) |

```php
use HelgeSverre\Synapse\Hooks\Events\{OnStreamStart, OnStreamChunk, OnStreamEnd};

$executor
    ->on(OnStreamStart::class, fn($e) => echo "Stream started\n")
    ->on(OnStreamChunk::class, fn($e) => null) // Each chunk
    ->on(OnStreamEnd::class, fn($e) => echo "\nStream ended: {$e->fullText}\n");
```
