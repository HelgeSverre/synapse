<?php

declare(strict_types=1);

/**
 * CLI Streaming Demo
 *
 * Demonstrates real-time streaming output from multiple LLM providers.
 * Tokens appear as they're generated, just like ChatGPT.
 *
 * Usage:
 *   php examples/streaming-cli.php                     # Uses OpenAI (default)
 *   php examples/streaming-cli.php anthropic           # Uses Anthropic
 *   php examples/streaming-cli.php mistral             # Uses Mistral
 *   php examples/streaming-cli.php moonshot            # Uses Moonshot (Kimi)
 *
 * Environment variables:
 *   OPENAI_API_KEY, ANTHROPIC_API_KEY, MISTRAL_API_KEY, MOONSHOT_API_KEY
 */

require_once __DIR__.'/../vendor/autoload.php';

use GuzzleHttp\Client;
use HelgeSverre\Synapse\Executor\CallableExecutor;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutor;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutorWithFunctions;
use HelgeSverre\Synapse\Executor\UseExecutors;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\Provider\Anthropic\AnthropicProvider;
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use HelgeSverre\Synapse\Provider\Mistral\MistralProvider;
use HelgeSverre\Synapse\Provider\Moonshot\MoonshotProvider;
use HelgeSverre\Synapse\Provider\OpenAI\OpenAIProvider;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\TextDelta;
use HelgeSverre\Synapse\Streaming\ToolCallsReady;

// ANSI colors for terminal output
const CYAN = "\033[36m";
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const MAGENTA = "\033[35m";
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";

function createProvider(string $name): array
{
    $transport = new GuzzleStreamTransport(new Client(['timeout' => 60]));

    return match ($name) {
        'openai' => [
            new OpenAIProvider($transport, getenv('OPENAI_API_KEY') ?: exit("OPENAI_API_KEY not set\n")),
            'gpt-4o-mini',
        ],
        'anthropic' => [
            new AnthropicProvider($transport, getenv('ANTHROPIC_API_KEY') ?: exit("ANTHROPIC_API_KEY not set\n")),
            'claude-3-haiku-20240307',
        ],
        'mistral' => [
            new MistralProvider($transport, getenv('MISTRAL_API_KEY') ?: exit("MISTRAL_API_KEY not set\n")),
            'mistral-small-latest',
        ],
        'moonshot' => [
            new MoonshotProvider($transport, getenv('MOONSHOT_API_KEY') ?: exit("MOONSHOT_API_KEY not set\n")),
            'moonshot-v1-8k',
        ],
        default => exit("Unknown provider: {$name}. Use: openai, anthropic, mistral, or moonshot\n"),
    };
}

function printHeader(string $title): void
{
    echo "\n".BOLD.CYAN."━━━ {$title} ━━━".RESET."\n\n";
}

function printInfo(string $label, string $value): void
{
    echo DIM."{$label}: ".RESET.$value."\n";
}

// Get provider from command line
$providerName = $argv[1] ?? 'openai';
[$provider, $model] = createProvider($providerName);

echo BOLD."\n🚀 LLM Streaming Demo".RESET."\n";
printInfo('Provider', $providerName);
printInfo('Model', $model);

// Demo 1: Simple text streaming
printHeader('Demo 1: Simple Text Streaming');
echo YELLOW.'Prompt: '.RESET."Write a haiku about PHP programming.\n\n";
echo GREEN.'Response: '.RESET;

$prompt = (new TextPrompt)->setContent('Write a haiku about PHP programming. Just the haiku, nothing else.');
$executor = new StreamingLlmExecutor($provider, $prompt, $model, maxTokens: 100);

$startTime = microtime(true);
foreach ($executor->stream([]) as $event) {
    if ($event instanceof TextDelta) {
        echo $event->text;
        flush();
    }
}
$duration = round((microtime(true) - $startTime) * 1000);
echo "\n\n".DIM."[Completed in {$duration}ms]".RESET."\n";

// Demo 2: Longer streaming response
printHeader('Demo 2: Longer Response Streaming');
echo YELLOW.'Prompt: '.RESET."Explain recursion in 3 sentences.\n\n";
echo GREEN.'Response: '.RESET;

$prompt = (new TextPrompt)->setContent('Explain recursion in exactly 3 sentences. Be concise.');
$executor = new StreamingLlmExecutor($provider, $prompt, $model, maxTokens: 150);

$startTime = microtime(true);
$result = $executor->streamAndCollect([]);
// streamAndCollect doesn't show streaming, so let's use stream() instead
$prompt = (new TextPrompt)->setContent('Explain recursion in exactly 3 sentences. Be concise.');
$executor = new StreamingLlmExecutor($provider, $prompt, $model, maxTokens: 150);
foreach ($executor->stream([]) as $event) {
    if ($event instanceof TextDelta) {
        echo $event->text;
        flush();
    }
    if ($event instanceof StreamCompleted && $event->usage) {
        $usage = $event->usage;
    }
}
$duration = round((microtime(true) - $startTime) * 1000);
echo "\n\n".DIM."[Completed in {$duration}ms";
if (isset($usage)) {
    echo " | Tokens: {$usage->inputTokens} in, {$usage->outputTokens} out";
}
echo ']'.RESET."\n";

// Demo 3: Tool calling with streaming (if supported)
if ($providerName !== 'moonshot') { // Moonshot tool support varies by model
    printHeader('Demo 3: Streaming with Tool Calls');

    $tools = new UseExecutors([
        new CallableExecutor(
            name: 'get_weather',
            description: 'Get current weather for a city',
            handler: function (array $input): string {
                $city = $input['city'] ?? 'Unknown';
                echo "\n".MAGENTA."  [Tool: get_weather({$city})]".RESET."\n";
                usleep(100000); // Simulate API call

                return json_encode([
                    'city' => $city,
                    'temperature' => rand(15, 30),
                    'condition' => ['sunny', 'cloudy', 'rainy'][rand(0, 2)],
                ]);
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'city' => ['type' => 'string', 'description' => 'City name'],
                ],
                'required' => ['city'],
            ],
        ),
        new CallableExecutor(
            name: 'calculate',
            description: 'Perform a calculation',
            handler: function (array $input): string {
                $expr = $input['expression'] ?? '0';
                echo "\n".MAGENTA."  [Tool: calculate({$expr})]".RESET."\n";
                // Safe eval for simple math
                $result = @eval("return {$expr};") ?? 'Error';

                return (string) $result;
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'expression' => ['type' => 'string', 'description' => 'Math expression'],
                ],
                'required' => ['expression'],
            ],
        ),
    ]);

    echo YELLOW.'Prompt: '.RESET."What's the weather in Oslo? Also, what is 42 * 17?\n\n";
    echo GREEN.'Response: '.RESET;

    $prompt = (new TextPrompt)->setContent(
        'What is the current weather in Oslo? Also calculate 42 * 17 for me. '
        .'Use the available tools to answer, then summarize the results.',
    );

    $executor = new StreamingLlmExecutorWithFunctions(
        $provider,
        $prompt,
        $model,
        $tools,
        maxTokens: 300,
    );

    $startTime = microtime(true);
    foreach ($executor->stream([]) as $event) {
        if ($event instanceof TextDelta) {
            echo $event->text;
            flush();
        }
        if ($event instanceof ToolCallsReady) {
            echo "\n".DIM.'  [Executing '.count($event->toolCalls).' tool(s)...]'.RESET;
        }
    }
    $duration = round((microtime(true) - $startTime) * 1000);
    echo "\n\n".DIM."[Completed in {$duration}ms]".RESET."\n";
}

// Interactive mode hint
printHeader('Interactive Mode');
echo "Try the interactive chat demo:\n";
echo CYAN."  php examples/streaming-chat-cli.php {$providerName}".RESET."\n\n";
