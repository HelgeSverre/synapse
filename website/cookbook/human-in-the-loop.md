# Human-in-the-Loop

Add approval steps where a human must confirm actions before they execute.

## The Pattern

Define tools that require user confirmation before performing side effects. The tool handler prompts the user and only proceeds with approval.

## Example: Approval Agent

```php
<?php

use function HelgeSverre\Synapse\{
    useLlm, createChatPrompt, createParser,
    createLlmExecutorWithFunctions, useExecutors,
};

$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]);

function requireApproval(string $action, array $details): bool
{
    echo "\n--- Approval Required ---\n";
    echo "Action: {$action}\n";
    foreach ($details as $key => $value) {
        echo "  {$key}: {$value}\n";
    }
    $response = readline("Approve? (yes/no): ");
    return strtolower(trim($response)) === 'yes';
}

$tools = useExecutors([
    [
        'name' => 'send_email',
        'description' => 'Send an email to a recipient',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'to' => ['type' => 'string', 'description' => 'Recipient email'],
                'subject' => ['type' => 'string', 'description' => 'Email subject'],
                'body' => ['type' => 'string', 'description' => 'Email body'],
            ],
            'required' => ['to', 'subject', 'body'],
        ],
        'handler' => function ($args) {
            if (!requireApproval('Send Email', $args)) {
                return 'User denied the email send.';
            }
            // Actually send the email here
            return "Email sent to {$args['to']}";
        },
    ],
    [
        'name' => 'delete_file',
        'description' => 'Delete a file from the system',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'File path to delete'],
            ],
            'required' => ['path'],
        ],
        'handler' => function ($args) {
            if (!requireApproval('Delete File', $args)) {
                return 'User denied the file deletion.';
            }
            unlink($args['path']);
            return "File deleted: {$args['path']}";
        },
    ],
]);

$executor = createLlmExecutorWithFunctions([
    'llm' => $llm,
    'prompt' => createChatPrompt()
        ->addSystemMessage(
            'You are a helpful assistant. When performing actions like sending ' .
            'emails or deleting files, the user will be asked to approve.'
        )
        ->addUserMessage('{{request}}', parseTemplate: true),
    'parser' => createParser('string'),
    'tools' => $tools,
]);

$result = $executor->execute([
    'request' => 'Send an email to john@example.com about the meeting tomorrow',
]);

echo $result->getValue();
```

## Tips

- Keep approval prompts clear and specific about what will happen
- Return informative messages when actions are denied so the LLM can respond appropriately
- Consider logging all approval decisions for audit trails
- Use `visibilityHandler` to hide dangerous tools until specific conditions are met
