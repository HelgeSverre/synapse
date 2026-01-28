<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use function LlmExe\createChatPrompt;
use function LlmExe\createLlmExecutorWithFunctions;
use function LlmExe\createParser;
use function LlmExe\useExecutors;
use function LlmExe\useLlm;

// Assume transport is configured...

$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);

// Define tools
$tools = useExecutors([
    [
        'name' => 'get_weather',
        'description' => 'Get the current weather for a location',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                    'description' => 'City name, e.g. "London, UK"',
                ],
                'unit' => [
                    'type' => 'string',
                    'enum' => ['celsius', 'fahrenheit'],
                    'description' => 'Temperature unit',
                ],
            ],
            'required' => ['location'],
        ],
        'handler' => function (array $args) {
            // In a real app, this would call a weather API
            $location = $args['location'];
            $unit = $args['unit'] ?? 'celsius';
            $temp = $unit === 'celsius' ? 22 : 72;

            return [
                'location' => $location,
                'temperature' => $temp,
                'unit' => $unit,
                'conditions' => 'Partly cloudy',
            ];
        },
    ],
    [
        'name' => 'calculate',
        'description' => 'Perform a mathematical calculation',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'expression' => [
                    'type' => 'string',
                    'description' => 'Mathematical expression to evaluate',
                ],
            ],
            'required' => ['expression'],
        ],
        'handler' => function (array $args) {
            // Simple and safe expression evaluator
            $expression = $args['expression'];
            // In production, use a proper math parser
            $result = eval("return {$expression};");

            return ['result' => $result];
        },
    ],
]);

$prompt = createChatPrompt()
    ->addSystemMessage('You are a helpful assistant with access to tools.')
    ->addUserMessage('{{question}}');

$executor = createLlmExecutorWithFunctions([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('string'),
    'model' => 'gpt-4o-mini',
    'tools' => $tools,
    'maxIterations' => 5,
]);

$result = $executor->execute([
    'question' => 'What is the weather like in Tokyo? Also, what is 15 * 7?',
]);

echo 'Response: '.$result->getValue()."\n";
