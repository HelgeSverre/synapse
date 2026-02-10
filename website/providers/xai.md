# xAI / Grok

## Setup

```php
$llm = useLlm('xai.grok-beta', [
    'apiKey' => getenv('XAI_API_KEY'),
]);

// "grok" prefix also works
$llm = useLlm('grok.grok-beta', [
    'apiKey' => getenv('XAI_API_KEY'),
]);
```

## Options

| Option | Type | Default |
|--------|------|---------|
| `apiKey` | `string` | Required |
| `baseUrl` | `string` | `https://api.x.ai/v1` |
| `transport` | `TransportInterface` | Auto-discovered |

## Models

```php
$llm = useLlm('xai.grok-beta', [...]);
$llm = useLlm('xai.grok-2', [...]);
```

## Example

```php
$llm = useLlm('xai.grok-beta', [
    'apiKey' => getenv('XAI_API_KEY'),
]);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => createChatPrompt()
        ->addSystemMessage('You are helpful.')
        ->addUserMessage('{{question}}', parseTemplate: true),
    'parser' => createParser('string'),
]);
```
