# Anthropic

## Setup

```php
$llm = useLlm('anthropic.claude-3-sonnet', [
    'apiKey' => getenv('ANTHROPIC_API_KEY'),
]);
```

## Options

| Option | Type | Default |
|--------|------|---------|
| `apiKey` | `string` | Required |
| `baseUrl` | `string` | `https://api.anthropic.com/v1` |
| `transport` | `TransportInterface` | Auto-discovered |
| `apiVersion` | `string` | `'2023-06-01'` |

::: warning
Anthropic does not support JSON mode (`responseFormat`). To get structured JSON output, use prompt instructions instead.
:::

## Models

```php
$llm = useLlm('anthropic.claude-3-opus', [...]);
$llm = useLlm('anthropic.claude-3-sonnet', [...]);
$llm = useLlm('anthropic.claude-3-haiku', [...]);
$llm = useLlm('anthropic.claude-3-5-sonnet-latest', [...]);
```

## Example

```php
$llm = useLlm('anthropic.claude-3-5-sonnet-latest', [
    'apiKey' => getenv('ANTHROPIC_API_KEY'),
]);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => createChatPrompt()
        ->addSystemMessage('You are a helpful assistant.')
        ->addUserMessage('{{question}}', parseTemplate: true),
    'parser' => createParser('string'),
    'model' => 'claude-3-5-sonnet-latest',
]);

$result = $executor->execute(['question' => 'What is Rust?']);
```
