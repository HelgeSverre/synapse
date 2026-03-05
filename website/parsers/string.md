# StringParser

The simplest parser. Trims whitespace from the response text and returns it.

## Factory

```php
$parser = createParser('string');
$parser = createParser('string', ['trim' => true]); // default
$parser = createParser('string', ['trim' => false]); // no trimming
```

## Options

| Option | Type   | Default | Description                |
| ------ | ------ | ------- | -------------------------- |
| `trim` | `bool` | `true`  | Whether to trim whitespace |

## Behavior

StringParser is the default parser when no parser is specified in `createExecutor()`. It extracts the text from the LLM response and optionally trims whitespace.

## Example

```php
$executor = createExecutor([
    'llm' => $llm,
    'prompt' => createChatPrompt()
        ->addSystemMessage('Respond concisely.')
        ->addUserMessage('{{question}}', parseTemplate: true),
    'parser' => createParser('string'),
]);

$result = $executor->run(['question' => 'What is PHP?']);
echo $result->getValue(); // "PHP is a server-side scripting language..."
```
