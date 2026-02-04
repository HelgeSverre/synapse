<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createLlmExecutor;
use function HelgeSverre\Synapse\createParser;

use HelgeSverre\Synapse\Factory;

use function HelgeSverre\Synapse\useLlm;

// Configure transport
$client = new \GuzzleHttp\Client;
$psr17Factory = new \GuzzleHttp\Psr7\HttpFactory;
$transport = Factory::createTransport($client, $psr17Factory, $psr17Factory);
Factory::setDefaultTransport($transport);

// Create an LLM provider
$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);

/**
 * Check statements against a conversation history.
 *
 * @param  array<string>  $statements  List of statements to validate
 * @param  array<array{role: string, content: string}>  $conversation  Conversation history
 * @param  string  $latestMessage  The latest user message
 * @return array{passed: bool, results: array<array{statement: string, answer: bool, confidence: float}>}
 */
function checkStatements(array $statements, array $conversation, string $latestMessage): array
{
    global $llm;

    // Format statements for the prompt
    $statementList = '';
    foreach ($statements as $index => $statement) {
        $statementList .= ($index + 1).". {$statement}\n";
    }

    // Format conversation history
    $conversationText = '';
    foreach ($conversation as $message) {
        $role = ucfirst($message['role']);
        $conversationText .= "{$role}: {$message['content']}\n";
    }

    // Define JSON schema for the response
    $schema = [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'statement' => ['type' => 'string'],
                'answer' => ['type' => 'boolean'],
                'confidence' => ['type' => 'number'],
            ],
            'required' => ['statement', 'answer', 'confidence'],
        ],
    ];

    // Create the validation prompt
    $prompt = createChatPrompt()
        ->addSystemMessage(<<<'PROMPT'
You are a statement validator. Given a conversation history and a list of statements, determine whether each statement is TRUE or FALSE based on the conversation.

For each statement, respond with:
- "statement": the original statement text
- "answer": true if the statement is supported by the conversation, false otherwise
- "confidence": a number between 0 and 1 indicating your confidence

Respond with a JSON array containing an object for each statement. Output ONLY the JSON array, nothing else.
PROMPT)
        ->addUserMessage(<<<PROMPT
Conversation:
{$conversationText}

Latest message: {$latestMessage}

Statements to validate:
{$statementList}
PROMPT);

    // Create a JSON parser
    $parser = createParser('json', [
        'schema' => $schema,
        'validateSchema' => false,
    ]);

    // Create the executor
    $executor = createLlmExecutor([
        'llm' => $llm,
        'prompt' => $prompt,
        'parser' => $parser,
        'model' => 'gpt-4o-mini',
    ]);

    $result = $executor->execute([]);
    $results = $result->getValue();

    // Determine if all statements passed
    $passed = true;
    foreach ($results as $item) {
        if ($item['answer'] === false) {
            $passed = false;
            break;
        }
    }

    return [
        'passed' => $passed,
        'results' => $results,
    ];
}

// Sample conversation
$conversation = [
    ['role' => 'user', 'content' => "Hi, I'm Alice"],
];

// Statements to validate
$statements = [
    'The user has told us their name',
    'The user has told us their age',
];

$latestMessage = $conversation[count($conversation) - 1]['content'];

echo "Statement Validator Demo\n";
echo str_repeat('=', 50)."\n\n";

echo "Conversation:\n";
foreach ($conversation as $message) {
    echo '  '.ucfirst($message['role']).": {$message['content']}\n";
}
echo "\n";

echo "Statements to validate:\n";
foreach ($statements as $index => $statement) {
    echo '  '.($index + 1).". {$statement}\n";
}
echo "\n";

$result = checkStatements($statements, $conversation, $latestMessage);

echo "Results:\n";
echo str_repeat('-', 50)."\n";

foreach ($result['results'] as $item) {
    $status = $item['answer'] ? '✓ PASS' : '✗ FAIL';
    $confidence = number_format($item['confidence'] * 100, 1);
    echo "{$status} - {$item['statement']} (confidence: {$confidence}%)\n";
}

echo "\n";
echo str_repeat('=', 50)."\n";
echo 'Overall: '.($result['passed'] ? 'ALL PASSED ✓' : 'SOME FAILED ✗')."\n";
