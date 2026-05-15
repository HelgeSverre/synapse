<?php

/**
 * ReAct-style agent loop running locally against Ollama with gemma4:latest.
 *
 * Prereqs:
 *   - `ollama serve` running on http://localhost:11434
 *   - `ollama pull gemma4:latest` (or set OLLAMA_MODEL env var to another tool-capable model)
 *
 * What this demonstrates:
 *   - Wiring the new OllamaProvider via useLlm('ollama')
 *   - A manual ReAct (Reason + Act) loop:
 *       1. Send conversation + tool schemas to the model
 *       2. If model returns tool_calls, run each tool and append a tool message
 *       3. Otherwise return the final text answer
 *       4. Bail after MAX_ITERATIONS to prevent runaway loops
 */

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Request\ToolDefinition;
use HelgeSverre\Synapse\State\Message;

use function HelgeSverre\Synapse\useLlm;

const MAX_ITERATIONS = 6;

$model = getenv('OLLAMA_MODEL') ?: 'gemma4:latest';

$llm = useLlm('ollama', [
    'baseUrl' => getenv('OLLAMA_BASE_URL') ?: 'http://localhost:11434/v1',
]);

/**
 * Tool implementations: each maps a name to a PHP callable.
 *
 * @var array<string, callable(array<string, mixed>): mixed>
 */
$toolHandlers = [
    'calculate' => function (array $args): array {
        $expression = (string) ($args['expression'] ?? '');

        // Allow only digits, whitespace, parentheses, and basic operators.
        if (! preg_match('/^[\d\s+\-*\/().]+$/', $expression)) {
            return ['error' => 'Expression contains disallowed characters'];
        }

        try {
            $result = @eval("return {$expression};");
        } catch (\Throwable $e) {
            return ['error' => 'Failed to evaluate: '.$e->getMessage()];
        }

        if (! is_numeric($result)) {
            return ['error' => 'Expression did not evaluate to a number'];
        }

        return ['result' => $result + 0];
    },

    'get_time' => function (array $args): array {
        $timezone = (string) ($args['timezone'] ?? 'UTC');

        try {
            $now = new DateTimeImmutable('now', new DateTimeZone($timezone));
        } catch (\Throwable) {
            return ['error' => "Unknown timezone: {$timezone}"];
        }

        return [
            'timezone' => $timezone,
            'iso' => $now->format(DATE_ATOM),
            'human' => $now->format('l, F j Y, H:i'),
        ];
    },

    'word_count' => function (array $args): array {
        $text = (string) ($args['text'] ?? '');

        return [
            'words' => str_word_count($text),
            'characters' => mb_strlen($text),
        ];
    },
];

// Tool schemas the model sees.
$tools = [
    new ToolDefinition(
        name: 'calculate',
        description: 'Evaluate a basic arithmetic expression. Supports + - * / and parentheses.',
        parameters: [
            'type' => 'object',
            'properties' => [
                'expression' => [
                    'type' => 'string',
                    'description' => 'Arithmetic expression, e.g. "(12 + 8) * 3"',
                ],
            ],
            'required' => ['expression'],
        ],
    ),
    new ToolDefinition(
        name: 'get_time',
        description: 'Get the current date and time in a given IANA timezone.',
        parameters: [
            'type' => 'object',
            'properties' => [
                'timezone' => [
                    'type' => 'string',
                    'description' => 'IANA timezone name, e.g. "Europe/Oslo" or "America/New_York"',
                ],
            ],
            'required' => ['timezone'],
        ],
    ),
    new ToolDefinition(
        name: 'word_count',
        description: 'Count words and characters in a piece of text.',
        parameters: [
            'type' => 'object',
            'properties' => [
                'text' => ['type' => 'string', 'description' => 'Text to analyze'],
            ],
            'required' => ['text'],
        ],
    ),
];

$systemPrompt = <<<'PROMPT'
You are a careful, concise assistant with access to tools. Reasoning policy:
- If a question needs a calculation, a current timestamp, or counting words, CALL the matching tool.
- Never invent numerical results — always go through `calculate`.
- After receiving tool output, integrate it into a short, plain-English answer.
- Stop calling tools once you have enough information to answer.
PROMPT;

$question = $argv[1] ?? 'What is (137 + 84) * 3, and what is the current time in Europe/Oslo?';

/** @var list<Message> $messages */
$messages = [Message::user($question)];

echo "Question: {$question}\n";
echo str_repeat('-', 60)."\n";

for ($iteration = 1; $iteration <= MAX_ITERATIONS; $iteration++) {
    echo "\n[iteration {$iteration}]\n";

    $request = new GenerationRequest(
        model: $model,
        messages: $messages,
        temperature: 0.0,
        tools: $tools,
        systemPrompt: $systemPrompt,
    );

    $response = $llm->provider->generate($request);

    if ($response->toolCalls === []) {
        echo "Final answer:\n  ".trim((string) $response->text)."\n";
        if ($response->usage !== null) {
            echo "\nTokens: input={$response->usage->inputTokens} output={$response->usage->outputTokens}\n";
        }
        exit(0);
    }

    // Append the assistant's tool-call message before any tool replies.
    $messages[] = Message::assistant($response->text ?? '', $response->toolCalls);

    foreach ($response->toolCalls as $toolCall) {
        $name = $toolCall->name;
        $args = $toolCall->arguments;

        echo "  -> tool call: {$name}(".json_encode($args, JSON_UNESCAPED_SLASHES).")\n";

        if (! isset($toolHandlers[$name])) {
            $result = ['error' => "Unknown tool: {$name}"];
        } else {
            try {
                $result = $toolHandlers[$name]($args);
            } catch (\Throwable $e) {
                $result = ['error' => $e->getMessage()];
            }
        }

        $resultJson = json_encode($result, JSON_UNESCAPED_SLASHES);
        echo "  <- result:    {$resultJson}\n";

        $messages[] = Message::tool($resultJson, $toolCall->id, $name);
    }
}

fwrite(STDERR, "Agent exceeded MAX_ITERATIONS without producing a final answer.\n");
exit(1);
