# Groq

Groq is not available via the `useLlm()` factory. Instantiate it directly.

## Setup

```php
use HelgeSverre\Synapse\Provider\Groq\GroqProvider;
use HelgeSverre\Synapse\Factory;

$llm = new GroqProvider(
    transport: Factory::getDefaultTransport(),
    apiKey: getenv('GROQ_API_KEY'),
    baseUrl: 'https://api.groq.com/openai/v1', // default
);
```

## Usage

```php
$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => createChatPrompt()
        ->addSystemMessage('You are helpful.')
        ->addUserMessage('{{question}}', parseTemplate: true),
    'parser' => createParser('string'),
    'model' => 'llama-3.1-70b-versatile',
]);

$result = $executor->execute(['question' => 'What is Groq?']);
```
