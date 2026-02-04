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
 * Generate an answer to a question with constraints.
 */
function generateAnswer(mixed $llm, string $question, string $requiredWord): string
{
    $prompt = createChatPrompt()
        ->addSystemMessage('You are a helpful assistant. Answer questions concisely.')
        ->addUserMessage(
            'Answer this question in under 10 words. You MUST include the word "{{requiredWord}}" in your answer.

Question: {{question}}

Answer:',
            parseTemplate: true,
        );

    $executor = createLlmExecutor([
        'llm' => $llm,
        'prompt' => $prompt,
        'parser' => createParser('string'),
        'model' => 'gpt-4o-mini',
    ]);

    $result = $executor->execute([
        'question' => $question,
        'requiredWord' => $requiredWord,
    ]);

    return trim($result->getValue());
}

/**
 * Check if an answer meets the criteria.
 * Returns an array with 'hasWord' and 'underLimit' booleans.
 */
function checkAnswer(mixed $llm, string $answer, string $requiredWord): array
{
    $prompt = createChatPrompt()
        ->addSystemMessage('You are a validator. Analyze the given answer and return a JSON object with validation results. Output ONLY valid JSON, nothing else.')
        ->addUserMessage(
            'Check if this answer meets the criteria:

Answer: "{{answer}}"
Required word: "{{requiredWord}}"
Word limit: 10 words

Return a JSON object with:
- "hasWord": boolean - true if the answer contains the required word (case-insensitive)
- "underLimit": boolean - true if the answer has fewer than 10 words

Output ONLY the JSON object:',
            parseTemplate: true,
        );

    $executor = createLlmExecutor([
        'llm' => $llm,
        'prompt' => $prompt,
        'parser' => createParser('json'),
        'model' => 'gpt-4o-mini',
    ]);

    $result = $executor->execute([
        'answer' => $answer,
        'requiredWord' => $requiredWord,
    ]);

    return $result->getValue();
}

/**
 * Generate and refine an answer until it meets criteria, up to maxAttempts.
 */
function getRefinedAnswer(mixed $llm, string $question, string $requiredWord, int $maxAttempts = 3): array
{
    $attempt = 0;
    $answer = '';
    $meetsAllCriteria = false;

    while ($attempt < $maxAttempts && ! $meetsAllCriteria) {
        $attempt++;
        echo "Attempt {$attempt}:\n";

        // Generate an answer
        $answer = generateAnswer($llm, $question, $requiredWord);
        echo "  Generated: \"{$answer}\"\n";

        // Check if it meets criteria
        $check = checkAnswer($llm, $answer, $requiredWord);
        echo '  Validation: hasWord='.($check['hasWord'] ? 'true' : 'false')
            .', underLimit='.($check['underLimit'] ? 'true' : 'false')."\n";

        $meetsAllCriteria = $check['hasWord'] && $check['underLimit'];

        if ($meetsAllCriteria) {
            echo "  ✓ Answer meets all criteria!\n";
        } elseif ($attempt < $maxAttempts) {
            echo "  ✗ Criteria not met, retrying...\n";
        } else {
            echo "  ✗ Max attempts reached.\n";
        }

        echo "\n";
    }

    return [
        'answer' => $answer,
        'attempts' => $attempt,
        'success' => $meetsAllCriteria,
    ];
}

// Run the self-refinement loop
echo "=== Self-Refinement Loop Example ===\n\n";
echo "Question: What is the sun?\n";
echo "Required word: star\n";
echo "Max words: 10\n\n";

$result = getRefinedAnswer($llm, 'What is the sun?', 'star');

echo "=== Final Result ===\n";
echo "Answer: \"{$result['answer']}\"\n";
echo "Attempts: {$result['attempts']}\n";
echo 'Success: '.($result['success'] ? 'Yes' : 'No')."\n";
