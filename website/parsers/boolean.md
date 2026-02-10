# BooleanParser

Detects yes/no, true/false answers from LLM responses and returns a PHP `bool`.

## Factory

```php
$parser = createParser('boolean');
$parser = createParser('bool');
```

## Behavior

Matches common affirmative/negative patterns in the response text:
- **true**: "yes", "true", "1", "correct", etc.
- **false**: "no", "false", "0", "incorrect", etc.

## Example

```php
$prompt = createChatPrompt()
    ->addSystemMessage('Answer with "yes" or "no" only.')
    ->addUserMessage('Is "{{text}}" appropriate content?', parseTemplate: true);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('boolean'),
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute(['text' => 'The weather is lovely today.']);
$result->getValue(); // true

$result = $executor->execute(['text' => 'Some inappropriate content...']);
$result->getValue(); // false
```

## Use Cases

- Content moderation (is this appropriate?)
- Fact checking (is this statement true?)
- Classification (is this spam?)
- Validation (does this meet criteria?)
