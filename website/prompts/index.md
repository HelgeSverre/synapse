# Prompts

Prompts define what gets sent to the LLM. Synapse provides two prompt types with a shared template engine.

## Prompt Types

| Type | Returns | Use Case |
|------|---------|----------|
| [ChatPrompt](/prompts/chat-prompt) | `Message[]` | Structured conversations with roles |
| [TextPrompt](/prompts/text-prompt) | `string` | Simple text completions |

## Template Variables

Both prompt types support <code v-pre>{{variable}}</code> syntax for dynamic content:

```php
$prompt = createChatPrompt()
    ->addSystemMessage('You are an expert on {{topic}}.')
    ->addUserMessage('{{question}}', parseTemplate: true);

$messages = $prompt->render([
    'topic' => 'history',
    'question' => 'Who was Napoleon?',
]);
```

::: tip parseTemplate Parameter
`addUserMessage()` defaults to `parseTemplate: false`. You must pass `parseTemplate: true` to enable template rendering for user messages. System messages always parse templates.
:::

## Quick Example

```php
use function HelgeSverre\Synapse\{createChatPrompt, createTextPrompt};

// Chat prompt (recommended for most use cases)
$chat = createChatPrompt()
    ->addSystemMessage('You are a helpful assistant.')
    ->addUserMessage('{{question}}', parseTemplate: true);

// Text prompt (simple completions)
$text = createTextPrompt()
    ->addContent('Answer this question: {{question}}');
```

See [Template Engine](/prompts/template-engine) for advanced features like helpers, partials, and strict mode.
