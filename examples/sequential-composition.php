<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use function LlmExe\createChatPrompt;
use function LlmExe\createLlmExecutor;
use function LlmExe\createParser;

use LlmExe\Factory;

use function LlmExe\useLlm;

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
 * Generate an outline for a story based on an idea.
 * Returns an array of bullet points.
 */
function generateOutline(mixed $llm, string $idea): array
{
    $prompt = createChatPrompt()
        ->addSystemMessage('You are a creative story planner. Generate concise story outlines.')
        ->addUserMessage(
            'Create a story outline for the following idea. Return 4-6 bullet points, one per line.

Idea: {{idea}}

Outline:',
            parseTemplate: true,
        );

    $executor = createLlmExecutor([
        'llm' => $llm,
        'prompt' => $prompt,
        'parser' => createParser('list'),
        'model' => 'gpt-4o-mini',
    ]);

    $result = $executor->execute([
        'idea' => $idea,
    ]);

    return $result->getValue();
}

/**
 * Write a full story based on an outline.
 */
function writeStory(mixed $llm, array $outline): string
{
    // Format the outline as bullet points
    $outlineText = implode("\n", array_map(fn ($point) => "• {$point}", $outline));

    $prompt = createChatPrompt()
        ->addSystemMessage('You are a creative fiction writer. Write engaging short stories based on outlines.')
        ->addUserMessage(
            'Write a short story (2-3 paragraphs) based on this outline:

{{outline}}

Story:',
            parseTemplate: true,
        );

    $executor = createLlmExecutor([
        'llm' => $llm,
        'prompt' => $prompt,
        'parser' => createParser('string'),
        'model' => 'gpt-4o-mini',
    ]);

    $result = $executor->execute([
        'outline' => $outlineText,
    ]);

    return trim($result->getValue());
}

/**
 * Generate a complete story by chaining outline generation and story writing.
 */
function generateStory(mixed $llm, string $idea): array
{
    // Step 1: Generate the outline
    $outline = generateOutline($llm, $idea);

    // Step 2: Write the story from the outline
    $story = writeStory($llm, $outline);

    return [
        'outline' => $outline,
        'story' => $story,
    ];
}

// Run the sequential composition example
echo "=== Sequential Composition Example ===\n\n";

$idea = 'A robot learns to paint';
echo "Story Idea: {$idea}\n\n";

$result = generateStory($llm, $idea);

echo "=== Generated Outline ===\n";
foreach ($result['outline'] as $point) {
    echo "• {$point}\n";
}

echo "\n=== Final Story ===\n";
echo $result['story']."\n";
