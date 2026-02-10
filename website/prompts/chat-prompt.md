# ChatPrompt

The primary prompt type. Builds a sequence of role-based messages (system, user, assistant, tool).

## Usage

```php
use function HelgeSverre\Synapse\createChatPrompt;

$prompt = createChatPrompt()
    ->addSystemMessage('You are a helpful assistant who speaks {{language}}.')
    ->addUserMessage('{{question}}', parseTemplate: true);

$messages = $prompt->render([
    'language' => 'French',
    'question' => 'What is PHP?',
]);
// Returns Message[] with system and user messages
```

## Methods

### addSystemMessage(string $content)

Adds a system message. Templates are always parsed.

```php
$prompt->addSystemMessage('You are an expert on {{topic}}.');
```

### addUserMessage(string $content, ?string $name = null, bool $parseTemplate = false)

Adds a user message. The optional `$name` parameter can be used for user identification. Pass `parseTemplate: true` to enable <code v-pre>{{variable}}</code> replacement.

```php
$prompt->addUserMessage('Tell me about {{topic}}', parseTemplate: true);
$prompt->addUserMessage('Static message with no variables');
```

### addAssistantMessage(string $content)

Adds an assistant message (for few-shot examples):

```php
$prompt
    ->addUserMessage('What is 2+2?', parseTemplate: false)
    ->addAssistantMessage('4')
    ->addUserMessage('What is 3+3?', parseTemplate: false)
    ->addAssistantMessage('6')
    ->addUserMessage('What is {{a}}+{{b}}?', parseTemplate: true);
```

### addToolMessage(string $content, string $toolCallId, ?string $name = null)

Adds a tool result message:

```php
$prompt->addToolMessage(
    content: '{"temp": 22}',
    toolCallId: 'call_abc123',
    name: 'get_weather',
);
```

### addMessage(Role $role, string $content, ?string $name = null, bool $parseTemplate = true)

Adds a message with a specific role. Useful when you need to add messages with custom roles.

```php
use HelgeSverre\Synapse\State\Role;

$prompt->addMessage(Role::User, 'Hello world');
```

### addFromHistory(array $messages)

Adds an array of Message objects to the prompt. Useful for restoring conversation history.

```php
$prompt->addFromHistory($dialogue->getHistory());
```

### addHistoryPlaceholder(string $key)

Inserts conversation history from the input array:

```php
$prompt = createChatPrompt()
    ->addSystemMessage('You are helpful.')
    ->addHistoryPlaceholder('history')
    ->addUserMessage('{{message}}', parseTemplate: true);

// When executing, pass history as Message[] in the input
$result = $executor->execute([
    'history' => $previousMessages,
    'message' => 'What did I say earlier?',
]);
```

## Multi-Turn Conversation Example

```php
use HelgeSverre\Synapse\State\Message;

$prompt = createChatPrompt()
    ->addSystemMessage('You are a helpful assistant.')
    ->addHistoryPlaceholder('history')
    ->addUserMessage('{{message}}', parseTemplate: true);

$history = [];

// Turn 1
$result = $executor->execute([
    'history' => $history,
    'message' => 'My name is Alice.',
]);
$history[] = Message::user('My name is Alice.');
$history[] = Message::assistant($result->getValue());

// Turn 2
$result = $executor->execute([
    'history' => $history,
    'message' => 'What is my name?',
]);
// Assistant responds: "Your name is Alice."
```
