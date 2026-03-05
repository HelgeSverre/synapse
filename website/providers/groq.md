# Groq

Groq is available via the `useLlm()` factory.

## Setup

```php
use function HelgeSverre\Synapse\useLlm;

$llm = useLlm('groq', [
    'apiKey' => getenv('GROQ_API_KEY'),
    'model' => 'llama-3.1-70b-versatile',
]);
```

## Usage

```php
$executor = createExecutor([
    'llm' => $llm,
    'prompt' => createChatPrompt()
        ->addSystemMessage('You are helpful.')
        ->addUserMessage('{{question}}', parseTemplate: true),
    'parser' => createParser('string'),
]);

$result = $executor->run(['question' => 'What is Groq?']);
```
