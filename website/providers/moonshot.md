# Moonshot

Moonshot is available via the `useLlm()` factory.

## Setup

```php
use function HelgeSverre\Synapse\useLlm;

$llm = useLlm('moonshot', [
    'apiKey' => getenv('MOONSHOT_API_KEY'),
    'model' => 'moonshot-v1-8k',
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

$result = $executor->run(['question' => 'Hello']);
```
