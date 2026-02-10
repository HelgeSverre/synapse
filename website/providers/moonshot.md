# Moonshot

Moonshot is not available via the `useLlm()` factory. Instantiate it directly.

## Setup

```php
use HelgeSverre\Synapse\Provider\Moonshot\MoonshotProvider;
use HelgeSverre\Synapse\Factory;

$llm = new MoonshotProvider(
    transport: Factory::getDefaultTransport(),
    apiKey: getenv('MOONSHOT_API_KEY'),
    baseUrl: 'https://api.moonshot.ai/v1', // default
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
    'model' => 'moonshot-v1-8k',
]);

$result = $executor->execute(['question' => 'Hello']);
```
