# Configuration

## Transport

Synapse uses a transport layer to make HTTP requests to LLM APIs. The transport wraps PSR-18 HTTP clients.

### Auto-Discovery

If you have Guzzle or Symfony HTTP Client installed, Synapse auto-discovers them:

```php
// Just works — transport is auto-discovered
$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);
```

Discovery order:
1. **Guzzle** (`guzzlehttp/guzzle`) — tried first
2. **Symfony HTTP Client** (`symfony/http-client`) — fallback

### Manual Transport

Configure a custom transport with `Factory::setDefaultTransport()`:

```php
use HelgeSverre\Synapse\Factory;

$client = new \GuzzleHttp\Client(['timeout' => 30]);
$psr17Factory = new \GuzzleHttp\Psr7\HttpFactory();

Factory::setDefaultTransport(
    Factory::createTransport($client, $psr17Factory, $psr17Factory)
);
```

### Per-Provider Transport

Override the transport for a specific provider:

```php
$customTransport = Factory::createTransport($myClient, $myRequestFactory, $myStreamFactory);

$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
    'transport' => $customTransport,
]);
```

## Providers

The `useLlm()` factory creates providers using a `prefix.model` string:

| Prefix | Provider | Default Base URL |
|--------|----------|-----------------|
| `openai` | OpenAI | `https://api.openai.com/v1` |
| `anthropic` | Anthropic | `https://api.anthropic.com/v1` |
| `google`, `gemini` | Google Gemini | `https://generativelanguage.googleapis.com/v1beta` |
| `mistral` | Mistral | `https://api.mistral.ai/v1` |
| `xai`, `grok` | xAI | `https://api.x.ai/v1` |

### Common Options

All providers accept:

| Option | Type | Description |
|--------|------|-------------|
| `apiKey` | `string` | **Required.** API key for the provider |
| `baseUrl` | `string` | Override the default base URL |
| `transport` | `TransportInterface` | Override the transport for this provider |

```php
$llm = useLlm('openai.gpt-4o', [
    'apiKey' => getenv('OPENAI_API_KEY'),
    'baseUrl' => 'https://my-proxy.example.com/v1',
]);
```

### Direct Instantiation

Providers not in the factory (Groq, Moonshot) can be instantiated directly:

```php
use HelgeSverre\Synapse\Provider\Groq\GroqProvider;

$llm = new GroqProvider(
    transport: Factory::getDefaultTransport(),
    apiKey: getenv('GROQ_API_KEY'),
);
```

## Parsers

The `createParser()` factory accepts a type string and optional options:

| Type String | Parser | Description |
|-------------|--------|-------------|
| `'string'` | StringParser | Trims and returns text (default) |
| `'boolean'`, `'bool'` | BooleanParser | Detects yes/no/true/false |
| `'number'`, `'int'`, `'float'` | NumberParser | Extracts numeric values |
| `'json'` | JsonParser | Parses JSON with optional schema |
| `'list'`, `'array'` | ListParser | Splits by newlines into array |
| `'enum'` | EnumParser | Matches against allowed values |
| `'code'`, `'codeblock'`, `'markdownCodeBlock'` | MarkdownCodeBlockParser | Extracts code blocks |
| `'codeblocks'`, `'markdownCodeBlocks'` | MarkdownCodeBlocksParser | Extracts multiple code blocks |
| `'keyvalue'`, `'key-value'` | ListToKeyValueParser | Parses key:value lists |
| `'listjson'`, `'list-json'` | ListToJsonParser | Converts lists to JSON |
| `'template'`, `'replace'` | ReplaceStringTemplateParser | Template string replacement |
| `'function'`, `'tool'` | LlmFunctionParser | Parses tool call results |
| `'custom'` | CustomParser | Custom handler function |

See [Parsers](/parsers/) for detailed documentation of each parser.

## Environment Variables

A common pattern is to use environment variables for API keys:

```php
$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);
```

Example `.env` file:

```
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
GOOGLE_API_KEY=...
MISTRAL_API_KEY=...
XAI_API_KEY=...
```

## Executor Options

### LlmExecutor

```php
$executor = createLlmExecutor([
    'llm' => $provider,          // Required: LlmProviderInterface or Llm
    'prompt' => $prompt,         // Required: PromptInterface
    'parser' => $parser,         // Optional: ParserInterface (defaults to StringParser)
    'model' => 'gpt-4o-mini',   // Optional when using useLlm('provider.model')
    'temperature' => 0.7,       // Optional: sampling temperature
    'maxTokens' => 1000,        // Optional: max output tokens
    'responseFormat' => null,    // Optional: response format hint
    'name' => 'my-executor',    // Optional: executor name for debugging
    'hooks' => $hookDispatcher,  // Optional: HookDispatcherInterface
    'state' => $state,           // Optional: initial ConversationState
]);
```

### LlmExecutorWithFunctions

```php
$executor = createLlmExecutorWithFunctions([
    'llm' => $provider,          // Required: LlmProviderInterface or Llm
    'prompt' => $prompt,         // Required
    'parser' => $parser,         // Optional
    'model' => 'gpt-4o-mini',   // Optional when using useLlm('provider.model')
    'tools' => $tools,           // Required: UseExecutors or array of tool configs
    'maxIterations' => 10,       // Optional: max tool calling loops (default: 10)
    'temperature' => 0.7,       // Optional
    'maxTokens' => 1000,        // Optional
]);
```
