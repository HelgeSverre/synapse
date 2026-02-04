<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createLlmExecutor;
use function HelgeSverre\Synapse\createParser;

use HelgeSverre\Synapse\Factory;

use function HelgeSverre\Synapse\useLlm;

// Configure transport (using Guzzle as example)
$client = new \GuzzleHttp\Client;
$psr17Factory = new \GuzzleHttp\Psr7\HttpFactory;
$transport = Factory::createTransport($client, $psr17Factory, $psr17Factory);
Factory::setDefaultTransport($transport);

// Create an LLM provider
$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);

// Define available intents with descriptions
$intents = [
    'book_hotel' => 'User wants to book a hotel or accommodation',
    'book_flight' => 'User wants to book a flight or air travel',
    'rent_car' => 'User wants to rent a car or vehicle',
    'unknown' => 'Intent does not match any known category',
];

// Build the intent list for the prompt
$intentList = '';
foreach ($intents as $name => $description) {
    $intentList .= "- {$name}: {$description}\n";
}

// Define JSON schema for the response
$schema = [
    'type' => 'object',
    'properties' => [
        'intent' => ['type' => 'string'],
        'confidence' => ['type' => 'number'],
    ],
    'required' => ['intent', 'confidence'],
];

// Create the classification prompt
$prompt = createChatPrompt()
    ->addSystemMessage(<<<PROMPT
You are an intent classifier. Analyze the user's input and classify it into one of the following intents:

{$intentList}
Respond with a JSON object containing:
- "intent": the classified intent name (one of: book_hotel, book_flight, rent_car, unknown)
- "confidence": a number between 0 and 1 indicating your confidence

Output ONLY the JSON object, nothing else.
PROMPT)
    ->addUserMessage('{{input}}', parseTemplate: true);

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

// Example inputs to classify
$testInputs = [
    'I need to rent a car for my trip next week',
    'Can you help me find a hotel in Paris for 3 nights?',
    'I want to fly from New York to London next month',
    'What is the weather like today?',
];

echo "Intent Classification Demo\n";
echo str_repeat('=', 50)."\n\n";

foreach ($testInputs as $input) {
    $result = $executor->execute([
        'input' => $input,
    ]);

    $data = $result->getValue();

    echo "Input: \"{$input}\"\n";
    echo "Intent: {$data['intent']}\n";
    echo 'Confidence: '.number_format($data['confidence'] * 100, 1)."%\n";
    echo "\n";
}
