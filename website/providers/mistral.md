# Mistral

## Setup

```php
$llm = useLlm('mistral.mistral-small-latest', [
    'apiKey' => getenv('MISTRAL_API_KEY'),
]);
```

## Options

| Option | Type | Default |
|--------|------|---------|
| `apiKey` | `string` | Required |
| `baseUrl` | `string` | `https://api.mistral.ai/v1` |
| `transport` | `TransportInterface` | Auto-discovered |

## Models

```php
$llm = useLlm('mistral.mistral-small-latest', [...]);
$llm = useLlm('mistral.mistral-large-latest', [...]);
$llm = useLlm('mistral.open-mistral-nemo', [...]);
```

## Example

```php
$llm = useLlm('mistral.mistral-small-latest', [
    'apiKey' => getenv('MISTRAL_API_KEY'),
]);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => createChatPrompt()
        ->addSystemMessage('You are helpful.')
        ->addUserMessage('{{question}}', parseTemplate: true),
    'parser' => createParser('string'),
    'model' => 'mistral-small-latest',
]);
```
