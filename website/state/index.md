# State Management

Synapse provides two approaches to managing conversation state:

| Class | Style | Use Case |
|-------|-------|----------|
| [ConversationState](/state/conversation-state) | Immutable (`withX()`) | Functional pipelines, executor state |
| [Dialogue](/state/dialogue) | Mutable (fluent) | Multi-turn chat loops |

## Quick Example

```php
use HelgeSverre\Synapse\State\{ConversationState, Message, ContextItem};

// Immutable state
$state = new ConversationState();
$state = $state
    ->withMessage(Message::user('Hello'))
    ->withMessage(Message::assistant('Hi there!'))
    ->withContext(new ContextItem('user_id', '12345'));
```

```php
use function HelgeSverre\Synapse\createDialogue;

// Mutable dialogue
$dialogue = createDialogue('chat');
$dialogue->setUserMessage('Hello');
// After executor runs:
$dialogue->addFromOutput($result);
```
