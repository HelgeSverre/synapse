# LLM-Exe PHP

A modern PHP 8.2+ library for LLM orchestration with executors, prompts, parsers, and tool calling. This is a PHP adaptation of [llm-exe](https://github.com/gregreindel/llm-exe).

## Features

- **Executor Pattern**: Composable execution pipeline with lifecycle hooks
- **Prompt System**: Template-based prompts with variable substitution
- **Parser System**: Extract structured data from LLM responses (JSON, boolean, lists, etc.)
- **Tool/Function Calling**: Built-in support for LLM function calling
- **State Management**: Conversation history and context tracking
- **Multi-Provider**: OpenAI, Anthropic, and extensible for others
- **Event Hooks**: Lifecycle events for logging, metrics, and debugging
- **PSR Standards**: PSR-4, PSR-7, PSR-17, PSR-18 compatible

## Installation

```bash
composer require llm-exe/llm-exe
```

You'll also need an HTTP client (PSR-18) and HTTP factories (PSR-17):

```bash
composer require guzzlehttp/guzzle
```

## Quick Start

```php
<?php

use LlmExe\Factory;
use function LlmExe\{createChatPrompt, createLlmExecutor, createParser, useLlm};

// Configure HTTP transport
$client = new \GuzzleHttp\Client();
$psr17Factory = new \GuzzleHttp\Psr7\HttpFactory();
Factory::setDefaultTransport(
    Factory::createTransport($client, $psr17Factory, $psr17Factory)
);

// Create provider, prompt, and parser
$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]);

$prompt = createChatPrompt()
    ->addSystemMessage('You are a helpful assistant.')
    ->addUserMessage('{{question}}');

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

## Core Concepts

### Executors

Executors are the core building blocks that orchestrate the LLM pipeline.

```php
use function LlmExe\{createCoreExecutor, createLlmExecutor};

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

Prompts use `{{variable}}` syntax for template replacement.

```php
use function LlmExe\{createChatPrompt, createTextPrompt};

// Chat prompt (recommended)
$prompt = createChatPrompt()
    ->addSystemMessage('You are an expert on {{topic}}.')
    ->addUserMessage('{{question}}');

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
$prompt->addUserMessage('Hello {{user.name}}!');

// Custom helpers
$prompt->registerHelper('upper', fn($s) => strtoupper($s));
$prompt->addUserMessage('{{upper name}}'); // Uses helper

// Partials (reusable snippets)
$prompt->registerPartial('greeting', 'Hello, {{name}}!');
$prompt->addUserMessage('{{> greeting}}');

// Strict mode (throws on missing variables)
$prompt->strict(true);
```

### Parsers

Extract structured data from LLM responses.

```php
use function LlmExe\createParser;

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
use function LlmExe\{createLlmExecutorWithFunctions, useExecutors};

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

### State Management

```php
use LlmExe\State\{ConversationState, Message, ContextItem};

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
    ->addUserMessage('{{message}}');

$result = $executor->execute([
    'history' => $state->messages,
    'message' => 'What did I say?',
]);
```

### Event Hooks

```php
use LlmExe\Hooks\Events\{BeforeProviderCall, AfterProviderCall, OnSuccess, OnError};

$executor
    ->on(BeforeProviderCall::class, fn($e) => logger("Calling {$e->request->model}"))
    ->on(AfterProviderCall::class, fn($e) => logger("Used {$e->response->usage->getTotal()} tokens"))
    ->on(OnSuccess::class, fn($e) => logger("Completed in {$e->durationMs}ms"))
    ->on(OnError::class, fn($e) => logger("Error: {$e->error->getMessage()}"));
```

## Providers

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

### Custom Provider

Implement `LlmProviderInterface`:

```php
use LlmExe\Provider\{LlmProviderInterface, ProviderCapabilities};
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\Provider\Response\GenerationResponse;

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

## License

MIT
