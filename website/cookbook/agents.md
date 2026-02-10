# Building Agents

Create interactive agents that use tools to accomplish tasks.

## The Pattern

An agent is an `LlmExecutorWithFunctions` with a set of tools. The LLM decides which tools to use based on the conversation. Combine with `Dialogue` for multi-turn interactions.

## Example: CLI Research Agent

```php
<?php

use function HelgeSverre\Synapse\{
    useLlm, createChatPrompt, createParser, createDialogue,
    createLlmExecutorWithFunctions, useExecutors,
};

$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => getenv('OPENAI_API_KEY')]);

$tools = useExecutors([
    [
        'name' => 'search',
        'description' => 'Search for information on a topic',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Search query'],
            ],
            'required' => ['query'],
        ],
        'handler' => fn($args) => "Search results for '{$args['query']}': ...",
    ],
    [
        'name' => 'calculate',
        'description' => 'Evaluate a mathematical expression',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'expression' => ['type' => 'string', 'description' => 'Math expression'],
            ],
            'required' => ['expression'],
        ],
        'handler' => fn($args) => ['result' => eval("return {$args['expression']};")],
    ],
    [
        'name' => 'save_note',
        'description' => 'Save a note to memory',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string'],
            ],
            'required' => ['title', 'content'],
        ],
        'handler' => function ($args) {
            file_put_contents("/tmp/{$args['title']}.txt", $args['content']);
            return "Note saved: {$args['title']}";
        },
    ],
]);

$prompt = createChatPrompt()
    ->addSystemMessage(
        'You are a helpful research assistant. Use the available tools to help ' .
        'the user. Search for information, perform calculations, and save notes.'
    )
    ->addHistoryPlaceholder('history')
    ->addUserMessage('{{message}}', parseTemplate: true);

$executor = createLlmExecutorWithFunctions([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('string'),
    'tools' => $tools,
    'maxIterations' => 10,
]);

$dialogue = createDialogue('agent');

// Chat loop
while (true) {
    $input = readline('You: ');
    if ($input === 'quit') break;

    $dialogue->setUserMessage($input);

    $result = $executor->execute([
        'history' => $dialogue->getHistory(),
        'message' => $input,
    ]);

    echo "Agent: " . $result->getValue() . "\n\n";
    $dialogue->addFromOutput($result->response);
}
```

## Tips

- Write clear tool descriptions — the LLM uses these to decide when to use each tool
- Set `maxIterations` high enough for complex multi-step tasks
- Use `OnToolCall` hooks to log tool usage
- Consider `visibilityHandler` to show/hide tools based on context
