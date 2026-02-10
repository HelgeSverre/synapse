# Message & Role

## Message

Represents a single message in a conversation.

### Static Constructors

```php
use HelgeSverre\Synapse\State\Message;

$msg = Message::system('You are helpful.');
$msg = Message::user('What is PHP?');
$msg = Message::assistant('PHP is a scripting language.');
$msg = Message::tool(
    content: '{"temp": 22}',
    toolCallId: 'call_abc123',
    name: 'get_weather',
);
```

### Properties

```php
$msg->role;       // Role enum
$msg->content;    // string
$msg->toolCallId; // ?string (for tool messages)
$msg->name;       // ?string (for tool messages)
```

## Role Enum

```php
use HelgeSverre\Synapse\State\Role;

Role::System;    // System instructions
Role::User;      // User input
Role::Assistant;  // LLM response
Role::Tool;      // Tool result
```

## ContextItem

A key-value pair for conversation context:

```php
use HelgeSverre\Synapse\State\ContextItem;

$item = new ContextItem('user_id', '12345');
$item->key;   // 'user_id'
$item->value; // '12345'
```

Used with `ConversationState::withContext()`:

```php
$state = $state->withContext(new ContextItem('session', 'abc'));
```
