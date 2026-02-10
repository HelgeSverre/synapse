# Code Generation

Generate code using the code block parser to cleanly extract generated code from LLM responses.

## Example: Generate a PHP Class

```php
<?php

use function HelgeSverre\Synapse\{useLlm, createChatPrompt, createParser, createLlmExecutor};

$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]);

$prompt = createChatPrompt()
    ->addSystemMessage(
        'You are a PHP code generator. Generate clean, typed PHP 8.2+ code. ' .
        'Wrap your code in a PHP code block.'
    )
    ->addUserMessage('{{spec}}', parseTemplate: true);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('code', ['language' => 'php']),
]);

$result = $executor->execute([
    'spec' => 'Create a value object class called Money with amount (int, cents) ' .
              'and currency (string). Include add(), subtract(), and format() methods.',
]);

$code = $result->getValue();
echo $code['code'];
// <?php class Money { ... }
```

## Example: Generate Multiple Files

Use `codeblocks` parser for multi-file generation:

```php
$prompt = createChatPrompt()
    ->addSystemMessage(
        'Generate both a PHP class and its PHPUnit test. ' .
        'Use separate code blocks for each file.'
    )
    ->addUserMessage('{{spec}}', parseTemplate: true);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('codeblocks'),
]);

$result = $executor->execute([
    'spec' => 'A Stack class with push(), pop(), peek(), and isEmpty() methods.',
]);

$blocks = $result->getValue();
// [
//     ['language' => 'php', 'code' => 'class Stack { ... }'],
//     ['language' => 'php', 'code' => 'class StackTest extends TestCase { ... }'],
// ]

// Write files
foreach ($blocks as $i => $block) {
    file_put_contents("output_{$i}.php", "<?php\n\n" . $block['code']);
}
```

## Tips

- Be specific about the PHP version and coding standards in the system prompt
- Use `temperature: 0` for more deterministic code generation
- Validate generated code with PHPStan or `php -l` before using it
