# LlmExecutor

The standard executor that orchestrates the full LLM pipeline: render prompt, call provider, parse response.

## Usage

```php
use function HelgeSverre\Synapse\{useLlm, createChatPrompt, createParser, createLlmExecutor};

$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]);

$prompt = createChatPrompt()
    ->addSystemMessage('You are a helpful assistant.')
    ->addUserMessage('{{question}}', parseTemplate: true);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('string'),
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute(['question' => 'What is PHP?']);
echo $result->getValue();
```

## Factory Options

| Option | Type | Required | Default | Description |
|--------|------|----------|---------|-------------|
| `llm` | `LlmProviderInterface` | Yes | — | The LLM provider |
| `prompt` | `PromptInterface` | Yes | — | The prompt template |
| `parser` | `ParserInterface` | No | `StringParser` | Response parser |
| `model` | `string` | Yes | — | Model name |
| `temperature` | `float` | No | `null` | Sampling temperature |
| `maxTokens` | `int` | No | `null` | Max output tokens |
| `responseFormat` | `array` | No | `null` | Response format hint |
| `name` | `string` | No | `'LlmExecutor'` | Executor name |
| `hooks` | `HookDispatcherInterface` | No | `null` | Hook dispatcher |
| `state` | `ConversationState` | No | `null` | Initial state |

## How It Works

When you call `execute($input)`:

1. The prompt's `render($input)` method replaces template variables with values from `$input`
2. Rendered messages are sent to the provider via `generate()`
3. The parser extracts structured data from the response
4. State is updated with the assistant's message
5. An `ExecutionResult` is returned

## With JSON Output

```php
$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => createChatPrompt()
        ->addSystemMessage('Extract data as JSON.')
        ->addUserMessage('{{text}}', parseTemplate: true),
    'parser' => createParser('json', [
        'schema' => [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string'],
            ],
        ],
    ]),
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute(['text' => 'Contact John at john@example.com']);
// ['name' => 'John', 'email' => 'john@example.com']
```

## Supported Hook Events

| Event | When |
|-------|------|
| `BeforePromptRender` | Before template rendering |
| `AfterPromptRender` | After template rendering |
| `BeforeProviderCall` | Before the LLM API call |
| `AfterProviderCall` | After the LLM API responds |
| `OnSuccess` | Execution completed successfully |
| `OnError` | An exception occurred |
| `OnComplete` | After execution (success or failure) |

```php
use HelgeSverre\Synapse\Hooks\Events\{BeforeProviderCall, AfterProviderCall, OnSuccess};

$executor
    ->on(BeforeProviderCall::class, fn($e) => echo "Calling {$e->request->model}...")
    ->on(AfterProviderCall::class, fn($e) => echo "Used {$e->response->usage->getTotal()} tokens")
    ->on(OnSuccess::class, fn($e) => echo "Completed in {$e->durationMs}ms");

$result = $executor->execute(['question' => 'Hello']);
```

## Accessing Internals

```php
$executor->getProvider();  // LlmProviderInterface
$executor->getPrompt();    // PromptInterface
$executor->getParser();    // ParserInterface
$executor->getModel();     // string
$executor->getState();     // ConversationState
$executor->getMetadata();  // ExecutorMetadata
```
