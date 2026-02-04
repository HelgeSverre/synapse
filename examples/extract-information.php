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

$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);

// Define a schema for hotel booking extraction
$schema = [
    'type' => 'object',
    'properties' => [
        'city' => [
            'type' => 'string',
            'default' => 'unknown',
        ],
        'startDate' => [
            'type' => 'string',
            'default' => 'unknown',
        ],
        'endDate' => [
            'type' => 'string',
            'default' => 'unknown',
        ],
    ],
    'required' => ['city', 'startDate', 'endDate'],
];

$prompt = createChatPrompt()
    ->addSystemMessage('You are an assistant that extracts hotel booking information from user messages. Extract the city, start date, and end date. If any information is missing, use "unknown" as the value. Respond with ONLY valid JSON, no other text.')
    ->addUserMessage('Extract hotel booking information from this message: "{{input}}"

Respond with JSON in this format:
{
  "city": "city name or unknown",
  "startDate": "date or unknown",
  "endDate": "date or unknown"
}', parseTemplate: true);

$parser = createParser('json', [
    'schema' => $schema,
    'validateSchema' => false,
]);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => $parser,
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute([
    'input' => "I'm going to be in Berlin from the 14th to the 18th",
]);

$data = $result->getValue();

echo "Extracted Information:\n";
echo 'City: '.$data['city']."\n";
echo 'Start Date: '.$data['startDate']."\n";
echo 'End Date: '.$data['endDate']."\n";
