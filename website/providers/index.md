# Providers

Providers handle HTTP communication with LLM APIs. Synapse includes built-in providers for the major LLM platforms.

## The useLlm() Factory

```php
use function HelgeSverre\Synapse\useLlm;

$llm = useLlm('openai', [
    'apiKey' => getenv('OPENAI_API_KEY'),
    'model' => 'gpt-4o-mini',
]);
```

Use `prefix` for provider selection and set the model once (either in `useLlm()` options or in executor options). Avoid specifying two different model values.

```php
$llm = useLlm('openai', [
    'apiKey' => getenv('OPENAI_API_KEY'),
    'model' => 'gpt-4o-mini',
]);

// Model 'gpt-4o-mini' is used automatically
$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
]);

// If no model is provided via useLlm(), set it on the executor
$llmWithoutModel = useLlm('openai', ['apiKey' => getenv('OPENAI_API_KEY')]);
$executor = createLlmExecutor([
    'llm' => $llmWithoutModel,
    'prompt' => $prompt,
    'model' => 'gpt-4o-mini',
]);
```

`useLlm('provider.model')` is still supported, but mixing that with a different `model` value in executor options throws.

::: tip Streaming
All providers require `GuzzleStreamTransport` for streaming. See [Stream Transport](/streaming/transport) for setup.
:::

| Prefix             | Provider                           | Default Base URL                                   |
| ------------------ | ---------------------------------- | -------------------------------------------------- |
| `openai`           | [OpenAI](/providers/openai)        | `https://api.openai.com/v1`                        |
| `anthropic`        | [Anthropic](/providers/anthropic)  | `https://api.anthropic.com/v1`                     |
| `google`, `gemini` | [Google Gemini](/providers/google) | `https://generativelanguage.googleapis.com/v1beta` |
| `mistral`          | [Mistral](/providers/mistral)      | `https://api.mistral.ai/v1`                        |
| `xai`, `grok`      | [xAI / Grok](/providers/xai)       | `https://api.x.ai/v1`                              |
| `groq`             | [Groq](/providers/groq)            | `https://api.groq.com/openai/v1`                   |
| `moonshot`         | [Moonshot](/providers/moonshot)    | `https://api.moonshot.ai/v1`                       |

## LlmProviderInterface

All providers implement this interface:

```php
interface LlmProviderInterface
{
    public function generate(GenerationRequest $request): GenerationResponse;
    public function getCapabilities(): ProviderCapabilities;
    public function getName(): string;
}
```

## ProviderCapabilities

Each provider declares its capabilities:

```php
$caps = $llm->getCapabilities();
$caps->supportsTools;         // bool — tool/function calling
$caps->supportsStreaming;     // bool — token streaming
$caps->supportsJsonMode;     // bool — JSON mode
$caps->supportsVision;       // bool — image input
$caps->supportsSystemPrompt; // bool — system message role
```

## Common Options

All providers via `useLlm()` accept:

| Option      | Type                 | Description               |
| ----------- | -------------------- | ------------------------- |
| `apiKey`    | `string`             | **Required.** API key     |
| `baseUrl`   | `string`             | Override default base URL |
| `transport` | `TransportInterface` | Override HTTP transport   |

## GenerationRequest

Sent to `generate()`:

```php
$request = new GenerationRequest(
    model: 'gpt-4o-mini',
    messages: $messages,         // Message[]
    temperature: 0.7,            // ?float
    maxTokens: 1000,             // ?int
    tools: $toolDefinitions,     // ToolDefinition[]
    toolChoice: null,            // ?string
    responseFormat: null,        // ?array
    systemPrompt: null,          // ?string
    topP: null,                  // ?float
    stopSequences: null,         // ?array
    metadata: [],                // array
);
```

## GenerationResponse

Returned from `generate()`:

```php
$response->getText();              // string — response text
$response->getAssistantMessage();  // ?Message
$response->hasToolCalls();         // bool
$response->getToolCalls();         // ToolCall[]
$response->usage;                  // ?UsageInfo (inputTokens, outputTokens, getTotal())
$response->model;                  // string
$response->finishReason;           // ?string — why generation stopped
$response->raw;                    // array — full raw API response
```
