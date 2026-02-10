# Dialogue

A mutable helper for managing multi-turn conversations. Provides a fluent API for chat loops.

## Creating a Dialogue

```php
use function HelgeSverre\Synapse\createDialogue;

$dialogue = createDialogue('my-chat');
```

## Chat Loop Example

```php
$prompt = createChatPrompt()
    ->addSystemMessage('You are a helpful assistant.')
    ->addHistoryPlaceholder('history')
    ->addUserMessage('{{message}}', parseTemplate: true);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('string'),
    'model' => 'gpt-4o-mini',
]);

$dialogue = createDialogue('chat');

while (true) {
    $userInput = readline('You: ');
    if ($userInput === 'quit') break;

    $dialogue->setUserMessage($userInput);

    $result = $executor->execute([
        'history' => $dialogue->getHistory(),
        'message' => $userInput,
    ]);

    echo "Assistant: " . $result->getValue() . "\n";
    $dialogue->addFromOutput($result->response);
}
```

## Methods

### setUserMessage(string $message)

Set the current user message:

```php
$dialogue->setUserMessage('What is PHP?');
```

### addFromOutput(GenerationResponse $output)

Add the assistant's response from a generation response:

```php
$dialogue->addFromOutput($result->response);
```

### getHistory()

Get all messages as an array for injection into prompts:

```php
$history = $dialogue->getHistory(); // Message[]
```

### getLastMessage()

Get the last message of any role:

```php
$last = $dialogue->getLastMessage(); // ?Message
```

### addToolResult(ToolCall $toolCall, mixed $result)

Add a tool result message to the dialogue:

```php
$dialogue->addToolResult($toolCall, $result);
```

### addToolResults(GenerationResponse $response, array $results)

Add multiple tool results from a response that contained tool calls:

```php
$dialogue->addToolResults($response, $results);
```

### executeToolCalls(GenerationResponse $response, callable $executor)

Execute tool calls from a response and add results to the dialogue:

```php
$dialogue->executeToolCalls($response, function (ToolCall $call) {
    return match($call->name) {
        'get_weather' => getWeather($call->arguments),
        default => 'Unknown tool',
    };
});
```

### clear()

Reset the dialogue:

```php
$dialogue->clear();
```
