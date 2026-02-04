<?php

declare(strict_types=1);

/**
 * Interactive CLI Chat with Streaming
 *
 * A ChatGPT-like experience in your terminal with real-time streaming.
 * Maintains conversation history for multi-turn chats.
 *
 * Usage:
 *   php examples/streaming-chat-cli.php                # Uses OpenAI (default)
 *   php examples/streaming-chat-cli.php anthropic      # Uses Anthropic
 *   php examples/streaming-chat-cli.php mistral        # Uses Mistral
 *   php examples/streaming-chat-cli.php moonshot       # Uses Moonshot (Kimi)
 *
 * Commands:
 *   /clear  - Clear conversation history
 *   /exit   - Exit the chat
 *   /help   - Show available commands
 */

require_once __DIR__.'/../vendor/autoload.php';

use GuzzleHttp\Client;
use HelgeSverre\Synapse\Provider\Anthropic\AnthropicProvider;
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use HelgeSverre\Synapse\Provider\Mistral\MistralProvider;
use HelgeSverre\Synapse\Provider\Moonshot\MoonshotProvider;
use HelgeSverre\Synapse\Provider\OpenAI\OpenAIProvider;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\TextDelta;

// ANSI colors
const CYAN = "\033[36m";
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const RED = "\033[31m";
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";

function createProvider(string $name): array
{
    $transport = new GuzzleStreamTransport(new Client(['timeout' => 120]));

    return match ($name) {
        'openai' => [
            new OpenAIProvider($transport, getenv('OPENAI_API_KEY') ?: exit("OPENAI_API_KEY not set\n")),
            'gpt-4o-mini',
            'GPT-4o Mini',
        ],
        'anthropic' => [
            new AnthropicProvider($transport, getenv('ANTHROPIC_API_KEY') ?: exit("ANTHROPIC_API_KEY not set\n")),
            'claude-3-haiku-20240307',
            'Claude 3 Haiku',
        ],
        'mistral' => [
            new MistralProvider($transport, getenv('MISTRAL_API_KEY') ?: exit("MISTRAL_API_KEY not set\n")),
            'mistral-small-latest',
            'Mistral Small',
        ],
        'moonshot' => [
            new MoonshotProvider($transport, getenv('MOONSHOT_API_KEY') ?: exit("MOONSHOT_API_KEY not set\n")),
            'moonshot-v1-8k',
            'Moonshot v1 8K',
        ],
        default => exit("Unknown provider: {$name}. Use: openai, anthropic, mistral, or moonshot\n"),
    };
}

function printBanner(string $modelName): void
{
    echo BOLD.CYAN.'
╔═══════════════════════════════════════════════╗
║           LLM Streaming Chat Demo             ║
╚═══════════════════════════════════════════════╝'.RESET.'
  Model: '.GREEN.$modelName.RESET.'
  Type '.DIM.'/help'.RESET.' for commands, '.DIM.'/exit'.RESET.' to quit.
'.CYAN.'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━'.RESET.'
';
}

function printHelp(): void
{
    echo '
'.BOLD.'Available Commands:'.RESET.'
  '.CYAN.'/clear'.RESET.'  - Clear conversation history
  '.CYAN.'/exit'.RESET.'   - Exit the chat
  '.CYAN.'/help'.RESET.'   - Show this help message
  '.CYAN.'/stats'.RESET.'  - Show token usage statistics
';
}

// Get provider from command line
$providerName = $argv[1] ?? 'openai';
[$provider, $model, $modelDisplayName] = createProvider($providerName);

printBanner($modelDisplayName);

// Conversation state
$messages = [];
$totalInputTokens = 0;
$totalOutputTokens = 0;

$systemPrompt = 'You are a helpful, friendly assistant. Be concise but informative. '
    .'Use markdown formatting when helpful.';

// Main chat loop
while (true) {
    echo "\n".BOLD.GREEN.'You: '.RESET;

    $input = trim(fgets(STDIN) ?: '');

    if ($input === '') {
        continue;
    }

    // Handle commands
    if (str_starts_with($input, '/')) {
        $command = strtolower($input);

        if ($command === '/exit' || $command === '/quit') {
            echo DIM.'Goodbye!'.RESET."\n";
            break;
        }

        if ($command === '/clear') {
            $messages = [];
            echo DIM.'Conversation cleared.'.RESET."\n";

            continue;
        }

        if ($command === '/help') {
            printHelp();

            continue;
        }

        if ($command === '/stats') {
            echo DIM."Tokens used: {$totalInputTokens} input, {$totalOutputTokens} output".RESET."\n";

            continue;
        }

        echo RED.'Unknown command. Type /help for available commands.'.RESET."\n";

        continue;
    }

    // Add user message to history
    $messages[] = Message::user($input);

    // Build request with full conversation history
    $request = new GenerationRequest(
        model: $model,
        messages: $messages,
        systemPrompt: $systemPrompt,
        maxTokens: 1024,
    );

    // Stream the response
    echo "\n".BOLD.CYAN.'Assistant: '.RESET;

    $responseText = '';
    $usage = null;
    $startTime = microtime(true);

    try {
        foreach ($provider->stream($request) as $event) {
            if ($event instanceof TextDelta) {
                echo $event->text;
                $responseText .= $event->text;
                flush();
            }

            if ($event instanceof StreamCompleted) {
                $usage = $event->usage;
            }
        }
    } catch (Throwable $e) {
        echo "\n".RED.'Error: '.$e->getMessage().RESET."\n";
        // Remove the failed user message
        array_pop($messages);

        continue;
    }

    // Add assistant response to history
    if ($responseText !== '') {
        $messages[] = Message::assistant($responseText);
    }

    // Show stats
    $duration = round((microtime(true) - $startTime) * 1000);
    echo "\n".DIM."[{$duration}ms";

    if ($usage) {
        echo " | {$usage->inputTokens}+{$usage->outputTokens} tokens";
        $totalInputTokens += $usage->inputTokens;
        $totalOutputTokens += $usage->outputTokens;
    }

    echo ' | '.count($messages).' messages]'.RESET;
}

echo "\n";
