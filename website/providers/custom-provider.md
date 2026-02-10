# Custom Provider

Implement `LlmProviderInterface` to add support for any LLM API.

## Interface

```php
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;

class MyProvider implements LlmProviderInterface
{
    public function generate(GenerationRequest $request): GenerationResponse
    {
        // Make HTTP call to your API and return a response
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            supportsTools: false,
            supportsStreaming: false,
            supportsJsonMode: false,
            supportsVision: false,
            supportsSystemPrompt: true,
        );
    }

    public function getName(): string
    {
        return 'my-provider';
    }
}
```

## Full Example

```php
use HelgeSverre\Synapse\Provider\Http\TransportInterface;
use HelgeSverre\Synapse\Provider\Response\UsageInfo;
use HelgeSverre\Synapse\State\Message;

class MyProvider implements LlmProviderInterface
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.myprovider.com/v1',
    ) {}

    public function generate(GenerationRequest $request): GenerationResponse
    {
        // Build your API request
        $body = [
            'model' => $request->model,
            'messages' => array_map(fn(Message $m) => [
                'role' => $m->role->value,
                'content' => $m->content,
            ], $request->messages),
            'temperature' => $request->temperature,
            'max_tokens' => $request->maxTokens,
        ];

        // Make HTTP call via transport
        $response = $this->transport->send(
            method: 'POST',
            url: "{$this->baseUrl}/chat/completions",
            headers: [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            body: json_encode($body),
        );

        $data = json_decode($response->getBody()->getContents(), true);

        // Build and return GenerationResponse
        $text = $data['choices'][0]['message']['content'] ?? '';

        return new GenerationResponse(
            text: $text,
            messages: [Message::assistant($text)],
            toolCalls: [],
            model: $request->model,
            usage: new UsageInfo(
                inputTokens: $data['usage']['prompt_tokens'] ?? 0,
                outputTokens: $data['usage']['completion_tokens'] ?? 0,
            ),
        );
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            supportsTools: false,
            supportsStreaming: false,
            supportsJsonMode: false,
            supportsVision: false,
            supportsSystemPrompt: true,
        );
    }

    public function getName(): string
    {
        return 'my-provider';
    }
}
```

## Usage

```php
$llm = new MyProvider(
    transport: Factory::getDefaultTransport(),
    apiKey: getenv('MY_API_KEY'),
);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('string'),
    'model' => 'my-model',
]);
```
