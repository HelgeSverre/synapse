# Synapse

A modern PHP 8.2+ library for LLM orchestration with executors, prompts, parsers, streaming, and tool calling. Inspired by [llm-exe](https://github.com/gregreindel/llm-exe).

## Features

- **Executor Pattern**: Composable execution pipeline with lifecycle hooks
- **Prompt System**: Template-based prompts with helpers, partials, and history
- **Parser System**: Extract structured data from LLM responses (JSON, boolean, lists, enums, code blocks)
- **Tool/Function Calling**: Built-in support for multi-step tool calling
- **State Management**: Conversation history and context tracking
- **Streaming**: Token streaming and streaming tool calls
- **Multi-Provider**: OpenAI, Anthropic, Google/Gemini, Mistral, xAI (plus more via custom providers)
- **Embeddings**: Unified embedding providers (OpenAI, Mistral, Jina, Cohere, Voyage)
- **Event Hooks**: Lifecycle events for logging, metrics, and debugging
- **PSR Standards**: PSR-4, PSR-7, PSR-17, PSR-18 compatible

## Installation

```bash
composer require helgesverre/synapse
```

You'll also need an HTTP client (PSR-18) and HTTP factories (PSR-17). Synapse can auto-discover Guzzle or Symfony HTTP client if installed:

```bash
composer require guzzlehttp/guzzle
```

If you prefer Symfony:

```bash
composer require symfony/http-client
```

## Quick Start

```php
<?php

use function HelgeSverre\Synapse\{createChatPrompt, createLlmExecutor, createParser, useLlm};

// Create provider, prompt, and parser (transport auto-discovered if available)
$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]);

$prompt = createChatPrompt()
    ->addSystemMessage('You are a helpful assistant.')
    ->addUserMessage('{{question}}', parseTemplate: true);

$parser = createParser('string');

// Create and execute
$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => $parser,
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute(['question' => 'What is the capital of France?']);
echo $result->getValue(); // "Paris"
```

If you want to configure transport manually:

```php
<?php

use HelgeSverre\Synapse\Factory;

$client = new \GuzzleHttp\Client();
$psr17Factory = new \GuzzleHttp\Psr7\HttpFactory();
Factory::setDefaultTransport(
    Factory::createTransport($client, $psr17Factory, $psr17Factory)
);
```

## Core Concepts

### Executors

Executors are the core building blocks that orchestrate the LLM pipeline.

```php
use function HelgeSverre\Synapse\{createCoreExecutor, createLlmExecutor};

// CoreExecutor - wrap any function
$calc = createCoreExecutor(fn($input) => $input['a'] + $input['b']);
$result = $calc->execute(['a' => 5, 'b' => 3]);

// LlmExecutor - full LLM pipeline
$executor = createLlmExecutor([
    'llm' => $provider,
    'prompt' => $prompt,
    'parser' => $parser,
    'model' => 'gpt-4o-mini',
]);
```

### Prompts

Prompts use `{{variable}}` syntax for template replacement. Note: `addUserMessage()` defaults to `parseTemplate: false`, so pass `parseTemplate: true` when you want template rendering.

```php
use function HelgeSverre\Synapse\{createChatPrompt, createTextPrompt};

// Chat prompt (recommended)
$prompt = createChatPrompt()
    ->addSystemMessage('You are an expert on {{topic}}.')
    ->addUserMessage('{{question}}', parseTemplate: true);

// Text prompt (simple)
$prompt = createTextPrompt()
    ->addContent('Answer this question about {{topic}}: {{question}}');

// Render with values
$messages = $prompt->render([
    'topic' => 'history',
    'question' => 'Who was Napoleon?',
]);
```

#### Template Features

```php
// Nested paths
$prompt->addUserMessage('Hello {{user.name}}!', parseTemplate: true);

// Custom helpers
$prompt->registerHelper('upper', fn($s) => strtoupper($s));
$prompt->addUserMessage('{{upper name}}', parseTemplate: true); // Uses helper

// Partials (reusable snippets)
$prompt->registerPartial('greeting', 'Hello, {{name}}!');
$prompt->addUserMessage('{{> greeting}}', parseTemplate: true);

// Strict mode (throws on missing variables)
$prompt->strict(true);
```

### Parsers

Extract structured data from LLM responses.

```php
use function HelgeSverre\Synapse\createParser;

// String (default)
$parser = createParser('string');

// JSON with schema
$parser = createParser('json', [
    'schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'number'],
        ],
    ],
]);

// Boolean (yes/no detection)
$parser = createParser('boolean');

// Number
$parser = createParser('number');

// List/Array
$parser = createParser('list');

// Key-value list
$parser = createParser('keyvalue', ['separator' => ':']);

// List to JSON
$parser = createParser('listjson', ['separator' => ':']);

// Code block extraction
$parser = createParser('code', ['language' => 'php']);

// Enum (match from allowed values)
$parser = createParser('enum', [
    'values' => ['low', 'medium', 'high'],
]);

// Custom
$parser = createParser('custom', [
    'handler' => fn($response) => customParse($response->getText()),
]);
```

### Tool/Function Calling

```php
use function HelgeSverre\Synapse\{createLlmExecutorWithFunctions, useExecutors};

$tools = useExecutors([
    [
        'name' => 'get_weather',
        'description' => 'Get weather for a location',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'location' => ['type' => 'string'],
            ],
            'required' => ['location'],
        ],
        'handler' => fn($args) => ['temp' => 22, 'location' => $args['location']],
    ],
]);

$executor = createLlmExecutorWithFunctions([
    'llm' => $provider,
    'prompt' => $prompt,
    'parser' => createParser('string'),
    'model' => 'gpt-4o-mini',
    'tools' => $tools,
    'maxIterations' => 10,
]);
```

### Streaming

Streaming requires a stream-capable transport (for example `GuzzleStreamTransport`).

```php
use GuzzleHttp\Client;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutor;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use HelgeSverre\Synapse\Streaming\TextDelta;

$transport = new GuzzleStreamTransport(new Client(['timeout' => 60]));
$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
    'transport' => $transport,
]);

$prompt = (new TextPrompt)->setContent('Write a haiku about PHP.');
$executor = new StreamingLlmExecutor($llm, $prompt, 'gpt-4o-mini');

foreach ($executor->stream([]) as $event) {
    if ($event instanceof TextDelta) {
        echo $event->text;
    }
}
```

See `examples/streaming-cli.php` and `examples/streaming-chat-cli.php` for full demos.

### State Management

```php
use HelgeSverre\Synapse\State\{ConversationState, Message, ContextItem};

// Create state
$state = new ConversationState();

// Add messages
$state = $state
    ->withMessage(Message::user('Hello'))
    ->withMessage(Message::assistant('Hi there!'));

// Add context
$state = $state->withContext(new ContextItem('user_id', '12345'));

// Add attributes
$state = $state->withAttribute('session_start', time());

// Use in prompt
$prompt = createChatPrompt()
    ->addSystemMessage('You are helpful.')
    ->addHistoryPlaceholder('history')
    ->addUserMessage('{{message}}', parseTemplate: true);

$result = $executor->execute([
    'history' => $state->messages,
    'message' => 'What did I say?',
]);
```

### Event Hooks

```php
use HelgeSverre\Synapse\Hooks\Events\{BeforeProviderCall, AfterProviderCall, OnSuccess, OnError};

$executor
    ->on(BeforeProviderCall::class, fn($e) => logger("Calling {$e->request->model}"))
    ->on(AfterProviderCall::class, fn($e) => logger("Used {$e->response->usage->getTotal()} tokens"))
    ->on(OnSuccess::class, fn($e) => logger("Completed in {$e->durationMs}ms"))
    ->on(OnError::class, fn($e) => logger("Error: {$e->error->getMessage()}"));
```

### Embeddings

```php
use function HelgeSverre\Synapse\useEmbeddings;

$embeddings = useEmbeddings('openai', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);

$response = $embeddings->embed(
    'The quick brown fox jumps over the lazy dog.',
    'text-embedding-3-small',
);

$vector = $response->getEmbedding();
```

## Providers

`useLlm()` supports the following provider prefixes:

- `openai.*`
- `anthropic.*`
- `google.*` / `gemini.*`
- `mistral.*`
- `xai.*` / `grok.*`

Additional providers exist as classes (e.g. Groq, Moonshot) and can be instantiated directly if needed.

### OpenAI

```php
$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => 'sk-...',
    'baseUrl' => 'https://api.openai.com/v1', // optional
]);
```

### Anthropic

```php
$llm = useLlm('anthropic.claude-3-sonnet', [
    'apiKey' => 'sk-ant-...',
]);
```

### Google (Gemini)

```php
$llm = useLlm('google.gemini-1.5-flash', [
    'apiKey' => '...',
]);
```

### Mistral

```php
$llm = useLlm('mistral.mistral-small-latest', [
    'apiKey' => '...',
]);
```

### xAI (Grok)

```php
$llm = useLlm('xai.grok-beta', [
    'apiKey' => '...',
]);
```

### Custom Provider

Implement `LlmProviderInterface`:

```php
use HelgeSverre\Synapse\Provider\{LlmProviderInterface, ProviderCapabilities};
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;

class MyProvider implements LlmProviderInterface
{
    public function generate(GenerationRequest $request): GenerationResponse { ... }
    public function getCapabilities(): ProviderCapabilities { ... }
    public function getName(): string { return 'my-provider'; }
}
```

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Executor                              │
│  ┌─────────┐    ┌──────────┐    ┌────────┐    ┌─────────┐  │
│  │ Prompt  │ -> │ Provider │ -> │ Parser │ -> │ Result  │  │
│  └─────────┘    └──────────┘    └────────┘    └─────────┘  │
│       ↑              ↑              ↑                       │
│       │              │              │                       │
│   Template       HTTP Call      Extract                     │
│   Rendering      to LLM API     Structured                  │
│                                 Data                        │
└─────────────────────────────────────────────────────────────┘
```

## Testing

### Running Tests

```bash
# Run unit tests (default)
composer test
phpunit

# Run integration tests (requires API keys)
phpunit --testsuite=Integration
composer test:integration

# Run all tests
phpunit --testsuite=Unit,Integration
composer test:all
```

### Integration Tests

Integration tests require valid API keys set as environment variables:

- `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `MISTRAL_API_KEY`, `MOONSHOT_API_KEY`, `XAI_API_KEY`, `GOOGLE_API_KEY`, `GROQ_API_KEY`

Tests will automatically skip if the required API key is not set.

## License

MIT
