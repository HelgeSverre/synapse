# Getting Started

Synapse is a modern PHP 8.2+ library for LLM orchestration. It provides a composable, provider-agnostic pipeline for working with large language models — structured output parsing, streaming, tool calling, and agentic workflows, all built on PSR standards.

## Requirements

- PHP 8.2 or higher
- A PSR-18 HTTP client and PSR-17 HTTP factories

## Installation

Install Synapse via Composer:

```bash
composer require helgesverre/synapse
```

### HTTP Client

Synapse communicates with LLM APIs over HTTP using [PSR-18](https://www.php-fig.org/psr/psr-18/) and [PSR-17](https://www.php-fig.org/psr/psr-17/) standards. You need an HTTP client installed. Synapse auto-discovers Guzzle or Symfony HTTP Client if present.

**Guzzle** (most common):

```bash
composer require guzzlehttp/guzzle
```

**Symfony HTTP Client** (alternative):

```bash
composer require symfony/http-client
```

If both are installed, Guzzle takes precedence. For custom HTTP clients, see [Configuration](/guide/configuration).

## Quick Start

Here is a complete example that sends a question to OpenAI and gets a plain text response:

```php
<?php

use function HelgeSverre\Synapse\{useLlm, createChatPrompt, createParser, createLlmExecutor};

// 1. Create a provider
$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);

// 2. Build a prompt with template variables
$prompt = createChatPrompt()
    ->addSystemMessage('You are a helpful assistant.')
    ->addUserMessage('{{question}}', parseTemplate: true);

// 3. Choose a parser for the response format
$parser = createParser('string');

// 4. Wire it all together in an executor (model comes from useLlm)
$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => $parser,
]);

// 5. Execute with input variables
$result = $executor->execute(['question' => 'What is the capital of France?']);

echo $result->getValue(); // "Paris"
```

Every Synapse interaction follows this pattern:

1. **Provider** — which LLM API to call
2. **Prompt** — what to send (with template variables)
3. **Parser** — how to interpret the response
4. **Executor** — the pipeline that ties them together
5. **Execute** — run it with input data, get a result

## The Function API

Synapse exposes top-level functions in the `HelgeSverre\Synapse` namespace that wrap the `Factory` class:

```php
use function HelgeSverre\Synapse\{
    useLlm,
    createChatPrompt,
    createTextPrompt,
    createPrompt,
    createParser,
    createLlmExecutor,
    createLlmExecutorWithFunctions,
    createStreamingLlmExecutor,
    createStreamingLlmExecutorWithFunctions,
    createCoreExecutor,
    createCallableExecutor,
    useExecutors,
    useEmbeddings,
    createState,
    createDialogue,
};
```

These are thin wrappers over `Factory` static methods. You can use either style.

## Structured Output with JSON

Use the `json` parser to extract structured data from LLM responses:

```php
<?php

use function HelgeSverre\Synapse\{useLlm, createChatPrompt, createParser, createLlmExecutor};

$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);

$prompt = createChatPrompt()
    ->addSystemMessage('You are a data extraction assistant. Always respond with valid JSON.')
    ->addUserMessage('Extract the name and age from: "{{text}}"', parseTemplate: true);

$parser = createParser('json', [
    'schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'number'],
        ],
    ],
]);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => $parser,
]);

$result = $executor->execute([
    'text' => 'John Smith is 34 years old and lives in Oslo.',
]);

$data = $result->getValue();
// ['name' => 'John Smith', 'age' => 34]
```

## Switching Providers

Swap providers by changing the provider string and API key — everything else stays the same:

```php
// OpenAI
$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]);

// Anthropic
$llm = useLlm('anthropic.claude-3-sonnet', ['apiKey' => getenv('ANTHROPIC_API_KEY')]);

// Google Gemini
$llm = useLlm('google.gemini-1.5-flash', ['apiKey' => getenv('GOOGLE_API_KEY')]);

// Mistral
$llm = useLlm('mistral.mistral-small-latest', ['apiKey' => getenv('MISTRAL_API_KEY')]);

// xAI (Grok)
$llm = useLlm('xai.grok-beta', ['apiKey' => getenv('XAI_API_KEY')]);
```

## What's Next

- **[Configuration](/guide/configuration)** — Transport setup, provider options, and parser types
- **[Architecture](/guide/architecture)** — How the executor pipeline works
