# Google / Gemini

## Setup

```php
$llm = useLlm('google.gemini-1.5-flash', [
    'apiKey' => getenv('GOOGLE_API_KEY'),
]);

// "gemini" prefix also works
$llm = useLlm('gemini.gemini-1.5-flash', [
    'apiKey' => getenv('GOOGLE_API_KEY'),
]);
```

## Options

| Option | Type | Default |
|--------|------|---------|
| `apiKey` | `string` | Required |
| `baseUrl` | `string` | `https://generativelanguage.googleapis.com/v1beta` |
| `transport` | `TransportInterface` | Auto-discovered |

## Models

```php
$llm = useLlm('google.gemini-1.5-flash', [...]);
$llm = useLlm('google.gemini-1.5-pro', [...]);
$llm = useLlm('gemini.gemini-2.0-flash', [...]);
```

## Example

```php
$llm = useLlm('google.gemini-1.5-flash', [
    'apiKey' => getenv('GOOGLE_API_KEY'),
]);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => createChatPrompt()
        ->addSystemMessage('You are helpful.')
        ->addUserMessage('{{question}}', parseTemplate: true),
    'parser' => createParser('string'),
    'model' => 'gemini-1.5-flash',
]);

$result = $executor->execute(['question' => 'Explain quantum computing']);
```
