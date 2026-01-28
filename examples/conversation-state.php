<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use function LlmExe\createChatPrompt;
use function LlmExe\createLlmExecutor;
use function LlmExe\createParser;
use function LlmExe\createState;

use LlmExe\State\Message;

use function LlmExe\useLlm;

// Assume transport is configured...

$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);

// Create conversation state
$state = createState();

// Create prompt with history placeholder
$prompt = createChatPrompt()
    ->addSystemMessage('You are a helpful assistant.')
    ->addHistoryPlaceholder('history')
    ->addUserMessage('{{message}}', parseTemplate: true);

$parser = createParser('string');

// Function to chat
function chat(array &$history, string $message, $llm, $prompt, $parser): string
{
    $executor = createLlmExecutor([
        'llm' => $llm,
        'prompt' => $prompt,
        'parser' => $parser,
        'model' => 'gpt-4o-mini',
    ]);

    // Add user message to history
    $history[] = Message::user($message);

    $result = $executor->execute([
        'history' => $history,
        'message' => $message,
    ]);

    // Add assistant response to history
    $history[] = Message::assistant($result->getValue());

    return $result->getValue();
}

// Multi-turn conversation
$history = [];

echo "User: Hello, my name is Alice.\n";
$response = chat($history, 'Hello, my name is Alice.', $llm, $prompt, $parser);
echo "Assistant: {$response}\n\n";

echo "User: What's my name?\n";
$response = chat($history, "What's my name?", $llm, $prompt, $parser);
echo "Assistant: {$response}\n\n";

echo "User: Tell me a joke.\n";
$response = chat($history, 'Tell me a joke.', $llm, $prompt, $parser);
echo "Assistant: {$response}\n";
