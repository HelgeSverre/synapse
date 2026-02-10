# ConversationState

An immutable state container for messages, context, and attributes. Every mutation returns a new instance.

## Creating State

```php
use HelgeSverre\Synapse\State\{ConversationState, Message, ContextItem};

$state = new ConversationState();

// Or via factory
$state = createState();
```

## Adding Messages

```php
$state = $state->withMessage(Message::user('Hello'));
$state = $state->withMessage(Message::assistant('Hi!'));
$state = $state->withMessages([
    Message::user('Question'),
    Message::assistant('Answer'),
]);
```

## Context Items

Key-value pairs for additional context:

```php
$state = $state->withContext(new ContextItem('user_id', '12345'));
$state = $state->withContext(new ContextItem('session', 'abc'));

$item = $state->getContext('user_id');          // ?ContextItem
$userId = $item?->value;                        // '12345'

// Or use the convenience method:
$userId = $state->getContextValue('user_id');           // '12345'
$userId = $state->getContextValue('user_id', 'guest');  // with default
```

## Attributes

Arbitrary metadata:

```php
$state = $state->withAttribute('started_at', time());
$state = $state->withAttribute('model', 'gpt-4o-mini');

$startedAt = $state->getAttribute('started_at');
```

## Accessing Data

```php
$state->messages;    // Message[] — all messages
$state->context;     // array<string, ContextItem>
$state->attributes;  // array<string, mixed>
```

## With Executors

Executors automatically update state with assistant responses:

```php
$executor = createLlmExecutor([...])->withState($state);
$result = $executor->execute(['question' => 'Hello']);

$newState = $result->state; // Contains the new assistant message
```
