# TextPrompt

A simple prompt that produces a single text string. Best for basic completions where message roles aren't needed.

## Usage

```php
use function HelgeSverre\Synapse\createTextPrompt;

$prompt = createTextPrompt()
    ->addContent('Summarize the following text:\n\n{{text}}');

$rendered = $prompt->render(['text' => 'A long article...']);
// Returns a string: "Summarize the following text:\n\nA long article..."
```

## Methods

### setContent(string $content)

Set the entire prompt content (replaces any existing content):

```php
$prompt = createTextPrompt()
    ->setContent('Answer: {{question}}');
```

### addContent(string $content)

Append content to the prompt:

```php
$prompt = createTextPrompt()
    ->addContent('Context: {{context}}')
    ->addContent('\n\nQuestion: {{question}}');
```

## When to Use TextPrompt

Use `TextPrompt` when:
- You need a simple, single-string prompt
- The LLM API accepts raw text (not chat format)
- You're building simple completion tasks

Use [ChatPrompt](/prompts/chat-prompt) when:
- You need system/user/assistant message roles
- You're building conversational flows
- You need history management

## With an Executor

When a `TextPrompt` is used with `LlmExecutor`, the rendered text is wrapped in a user message automatically:

```php
$prompt = createTextPrompt()
    ->setContent('Translate to {{language}}: {{text}}');

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('string'),
]);

$result = $executor->execute([
    'language' => 'French',
    'text' => 'Hello, world!',
]);
```
