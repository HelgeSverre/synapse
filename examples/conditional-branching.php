<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use function LlmExe\createChatPrompt;
use function LlmExe\createLlmExecutor;
use function LlmExe\createParser;

use LlmExe\Factory;

use function LlmExe\useLlm;

// Configure transport (using Guzzle as example)
$client = new \GuzzleHttp\Client;
$psr17Factory = new \GuzzleHttp\Psr7\HttpFactory;
$transport = Factory::createTransport($client, $psr17Factory, $psr17Factory);
Factory::setDefaultTransport($transport);

// Create an LLM provider
$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);

/**
 * Classifies a question as 'technical' or 'creative'
 */
function classifyQuestion(object $llm, string $question): string
{
    $prompt = createChatPrompt()
        ->addSystemMessage(<<<'PROMPT'
You are a question classifier. Analyze the question and classify it as either:
- technical: Questions about facts, science, how things work, explanations
- creative: Questions about imagination, stories, hypotheticals, fiction

Respond with ONLY one word: either "technical" or "creative"
PROMPT)
        ->addUserMessage('{{question}}');

    $parser = createParser('enum', [
        'values' => ['technical', 'creative'],
    ]);

    $executor = createLlmExecutor([
        'llm' => $llm,
        'prompt' => $prompt,
        'parser' => $parser,
        'model' => 'gpt-4o-mini',
    ]);

    $result = $executor->execute(['question' => $question]);

    return $result->getValue();
}

/**
 * Provides a factual, concise technical answer
 */
function technicalAnswer(object $llm, string $question): string
{
    $prompt = createChatPrompt()
        ->addSystemMessage(<<<'PROMPT'
You are a knowledgeable expert. Provide factual, concise, and accurate answers.
Focus on scientific accuracy and clear explanations.
Keep your response to 2-3 sentences.
PROMPT)
        ->addUserMessage('{{question}}');

    $parser = createParser('string');

    $executor = createLlmExecutor([
        'llm' => $llm,
        'prompt' => $prompt,
        'parser' => $parser,
        'model' => 'gpt-4o-mini',
    ]);

    $result = $executor->execute(['question' => $question]);

    return $result->getValue();
}

/**
 * Provides an imaginative, story-like creative answer
 */
function creativeAnswer(object $llm, string $question): string
{
    $prompt = createChatPrompt()
        ->addSystemMessage(<<<'PROMPT'
You are a creative storyteller with a vivid imagination.
Provide imaginative, engaging, and story-like responses.
Use descriptive language and paint a picture with your words.
Keep your response to 3-4 sentences.
PROMPT)
        ->addUserMessage('{{question}}');

    $parser = createParser('string');

    $executor = createLlmExecutor([
        'llm' => $llm,
        'prompt' => $prompt,
        'parser' => $parser,
        'model' => 'gpt-4o-mini',
    ]);

    $result = $executor->execute(['question' => $question]);

    return $result->getValue();
}

/**
 * Classifies the question and routes to the appropriate answer function
 */
function answerQuestion(object $llm, string $question): array
{
    // Step 1: Classify the question
    $classification = classifyQuestion($llm, $question);

    // Step 2: Route to the appropriate executor based on classification
    if ($classification === 'technical') {
        $answer = technicalAnswer($llm, $question);
    } else {
        $answer = creativeAnswer($llm, $question);
    }

    return [
        'classification' => $classification,
        'answer' => $answer,
    ];
}

// Sample questions to demonstrate branching
$questions = [
    'How does photosynthesis work?',
    'What would a day in the life of a dragon be like?',
];

echo "Conditional Branching Demo\n";
echo str_repeat('=', 60)."\n\n";

foreach ($questions as $question) {
    echo "Question: {$question}\n";
    echo str_repeat('-', 60)."\n";

    $result = answerQuestion($llm, $question);

    echo "Classification: {$result['classification']}\n";
    echo "Answer: {$result['answer']}\n";
    echo "\n";
}
