<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createLlmExecutor;
use function HelgeSverre\Synapse\createParser;
use function HelgeSverre\Synapse\useLlm;

// Assume transport is configured...

$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);

// Define a JSON schema for the output
$schema = [
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string'],
        'capital' => ['type' => 'string'],
        'population' => ['type' => 'number'],
        'languages' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
        ],
    ],
    'required' => ['name', 'capital'],
];

$prompt = createChatPrompt()
    ->addSystemMessage('You are a helpful assistant that ONLY returns valid JSON. Never include any text before or after the JSON object.')
    ->addUserMessage('Return a JSON object about {{country}} with these fields: name, capital, population (number), and languages (array of strings). Output ONLY the JSON, nothing else.', parseTemplate: true);

$parser = createParser('json', [
    'schema' => $schema,
    'validateSchema' => false, // Set to true if you have a JSON Schema validator
]);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => $parser,
    'model' => 'gpt-4o-mini',
]);

$result = $executor->execute([
    'country' => 'Japan',
]);

$data = $result->getValue();

echo 'Country: '.$data['name']."\n";
echo 'Capital: '.$data['capital']."\n";
echo 'Population: '.number_format($data['population'] ?? 0)."\n";
echo 'Languages: '.implode(', ', $data['languages'] ?? [])."\n";
