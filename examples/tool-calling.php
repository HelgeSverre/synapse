<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createLlmExecutorWithFunctions;
use function HelgeSverre\Synapse\createParser;
use function HelgeSverre\Synapse\createToolRegistry;
use function HelgeSverre\Synapse\useLlm;

// Assume transport is configured...

$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);

// Define tools
$tools = createToolRegistry([
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
            $expression = $args['expression'];
            // Intentionally constrained example: "<number> <op> <number>"
            if (! preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*([+\-*\/])\s*(-?\d+(?:\.\d+)?)\s*$/', $expression, $matches)) {
                return ['error' => 'Unsupported expression format. Use: <number> <op> <number>'];
            }

            $left = (float) $matches[1];
            $operator = $matches[2];
            $right = (float) $matches[3];

            if ($operator === '/' && $right === 0.0) {
                return ['error' => 'Division by zero'];
            }

            $result = match ($operator) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => $left / $right,
            };

            if (floor($result) === $result) {
                $result = (int) $result;
            }

            return ['result' => $result];
        },
    ],
]);

$prompt = createChatPrompt()
    ->addSystemMessage('You are a helpful assistant with access to tools.')
    ->addUserMessage('{{question}}', parseTemplate: true);

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
