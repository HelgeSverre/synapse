<?php

declare(strict_types=1);

/**
 * Interactive Agentic CLI with Streaming and Tools
 *
 * An interactive agent that can use multiple tools to help answer questions
 * and complete tasks. Features real-time streaming with tool execution.
 *
 * Usage:
 *   php examples/agentic-agent-cli.php                # Uses OpenAI (default)
 *   php examples/agentic-agent-cli.php anthropic      # Uses Anthropic
 *   php examples/agentic-agent-cli.php xai            # Uses XAI (Grok)
 *   php examples/agentic-agent-cli.php groq           # Uses Groq (Llama)
 *   php examples/agentic-agent-cli.php google         # Uses Google (Gemini)
 *
 * Commands:
 *   /clear  - Clear conversation history and notes
 *   /stats  - Show token usage statistics
 *   /tools  - List available tools
 *   /help   - Show available commands
 *   /exit   - Exit the chat
 */

require_once __DIR__.'/../vendor/autoload.php';

require_once __DIR__.'/agentic-tools/CalculatorTool.php';
require_once __DIR__.'/agentic-tools/DateTimeTool.php';
require_once __DIR__.'/agentic-tools/NotesTool.php';
require_once __DIR__.'/agentic-tools/WeatherTool.php';
require_once __DIR__.'/agentic-tools/WebSearchTool.php';

use GuzzleHttp\Client;
use LlmExe\Examples\AgenticTools\CalculatorTool;
use LlmExe\Examples\AgenticTools\DateTimeTool;
use LlmExe\Examples\AgenticTools\NotesTool;
use LlmExe\Examples\AgenticTools\WeatherTool;
use LlmExe\Examples\AgenticTools\WebSearchTool;
use LlmExe\Executor\StreamingLlmExecutorWithFunctions;
use LlmExe\Executor\UseExecutors;
use LlmExe\Hooks\Events\OnToolCall;
use LlmExe\Hooks\HookDispatcher;
use LlmExe\Prompt\TextPrompt;
use LlmExe\Provider\Anthropic\AnthropicProvider;
use LlmExe\Provider\Google\GoogleProvider;
use LlmExe\Provider\Groq\GroqProvider;
use LlmExe\Provider\Http\GuzzleStreamTransport;
use LlmExe\Provider\OpenAI\OpenAIProvider;
use LlmExe\Provider\XAI\XAIProvider;
use LlmExe\State\Message;
use LlmExe\Streaming\StreamCompleted;
use LlmExe\Streaming\TextDelta;
use LlmExe\Streaming\ToolCallsReady;

// ANSI colors
const CYAN = "\033[36m";
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const MAGENTA = "\033[35m";
const RED = "\033[31m";
const BLUE = "\033[34m";
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";

function createProvider(string $name): array
{
    $transport = new GuzzleStreamTransport(new Client(['timeout' => 120]));

    return match ($name) {
        'openai' => [
            new OpenAIProvider($transport, getenv('OPENAI_API_KEY') ?: exit(RED."OPENAI_API_KEY not set\n".RESET)),
            'gpt-4o-mini',
            'GPT-4o Mini',
        ],
        'anthropic' => [
            new AnthropicProvider($transport, getenv('ANTHROPIC_API_KEY') ?: exit(RED."ANTHROPIC_API_KEY not set\n".RESET)),
            'claude-3-haiku-20240307',
            'Claude 3 Haiku',
        ],
        'xai' => [
            new XAIProvider($transport, getenv('XAI_API_KEY') ?: exit(RED."XAI_API_KEY not set\n".RESET)),
            'grok-3-mini-fast',
            'Grok 3 Mini Fast',
        ],
        'groq' => [
            new GroqProvider($transport, getenv('GROQ_API_KEY') ?: exit(RED."GROQ_API_KEY not set\n".RESET)),
            'meta-llama/llama-4-scout-17b-16e-instruct',
            'Llama 4 Scout (Groq)',
        ],
        'google' => [
            new GoogleProvider($transport, getenv('GOOGLE_API_KEY') ?: exit(RED."GOOGLE_API_KEY not set\n".RESET)),
            'gemini-2.0-flash',
            'Gemini 2.0 Flash',
        ],
        default => exit(RED."Unknown provider: {$name}. Use: openai, anthropic, xai, groq, or google\n".RESET),
    };
}

function printBanner(string $modelName): void
{
    echo BOLD.CYAN.'
╔═══════════════════════════════════════════════════════╗
║           Agentic Streaming CLI Demo                  ║
╚═══════════════════════════════════════════════════════╝'.RESET.'
  Model: '.GREEN.$modelName.RESET.'

  '.BOLD.'Available Tools:'.RESET.'
    🔢 '.YELLOW.'calculator'.RESET.'   - Mathematical calculations
    📅 '.YELLOW.'datetime'.RESET.'     - Date and time operations
    📝 '.YELLOW.'notes'.RESET.'        - Manage notes (add, list, get, delete)
    🌤️  '.YELLOW.'get_weather'.RESET.'  - Get weather for a city
    🔍 '.YELLOW.'web_search'.RESET.'   - Search the web

  '.BOLD.'Commands:'.RESET.' '.DIM.'/clear  /stats  /tools  /help  /exit'.RESET.'
'.CYAN.'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━'.RESET.'
';
}

function printHelp(): void
{
    echo '
'.BOLD.'Available Commands:'.RESET.'
  '.CYAN.'/clear'.RESET.'  - Clear conversation history and notes
  '.CYAN.'/stats'.RESET.'  - Show token usage statistics
  '.CYAN.'/tools'.RESET.'  - List available tools
  '.CYAN.'/help'.RESET.'   - Show this help message
  '.CYAN.'/exit'.RESET.'   - Exit the chat
';
}

function printTools(): void
{
    echo '
'.BOLD.'Available Tools:'.RESET.'
  🔢 '.YELLOW.'calculator'.RESET.'
     Perform mathematical calculations (+, -, *, /, ^, sqrt)

  📅 '.YELLOW.'datetime'.RESET.'
     Get current time, calculate date differences, add intervals

  📝 '.YELLOW.'notes'.RESET.'
     Manage notes: add, list, get, or delete notes

  🌤️  '.YELLOW.'get_weather'.RESET.'
     Get current weather for any city

  🔍 '.YELLOW.'web_search'.RESET.'
     Search the web for information
';
}

function formatToolCall(string $name, array $args): string
{
    $argsJson = json_encode($args);
    if (strlen($argsJson) > 60) {
        $argsJson = substr($argsJson, 0, 57).'...';
    }

    return MAGENTA."⚡ {$name}({$argsJson})".RESET;
}

// Get provider from command line
$providerName = $argv[1] ?? 'openai';
[$provider, $model, $modelDisplayName] = createProvider($providerName);

printBanner($modelDisplayName);

// Create tools
$tools = new UseExecutors([
    CalculatorTool::create(),
    DateTimeTool::create(),
    NotesTool::create(),
    WeatherTool::create(),
    WebSearchTool::create(),
]);

// System prompt
$systemPrompt = "You are a helpful assistant with access to the following tools:

1. **calculator** - Perform mathematical calculations. Supports +, -, *, /, ^ (power), sqrt(), and parentheses.
2. **datetime** - Get current date/time, calculate differences between dates, or add intervals to dates.
3. **notes** - Manage notes: add new notes, list all notes, get a specific note, or delete notes.
4. **get_weather** - Get current weather conditions for any city.
5. **web_search** - Search the web for information and get relevant results.

Guidelines:
- Use tools when they would be helpful to answer the user's question
- You can use multiple tools in a single response if needed
- Be concise and helpful in your responses
- When performing calculations or lookups, show the result clearly

Current date: ".date('Y-m-d');

// Prompt template
$prompt = (new TextPrompt)->setContent('{{message}}');

// Conversation state
$messages = [Message::system($systemPrompt)];
$totalInputTokens = 0;
$totalOutputTokens = 0;
$totalTurns = 0;

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
            $messages = [Message::system($systemPrompt)];
            NotesTool::reset();
            echo DIM.'Conversation and notes cleared.'.RESET."\n";

            continue;
        }

        if ($command === '/help') {
            printHelp();

            continue;
        }

        if ($command === '/stats') {
            $msgCount = count($messages) - 1; // Exclude system message
            echo DIM."Tokens: {$totalInputTokens} input, {$totalOutputTokens} output | Messages: {$msgCount} | Turns: {$totalTurns}".RESET."\n";

            continue;
        }

        if ($command === '/tools') {
            printTools();

            continue;
        }

        echo RED.'Unknown command. Type /help for available commands.'.RESET."\n";

        continue;
    }

    // Add user message to history
    $messages[] = Message::user($input);

    // Create hook dispatcher
    $hooks = new HookDispatcher;
    $toolCallCount = 0;

    $hooks->addListener(OnToolCall::class, function (OnToolCall $event) use (&$toolCallCount): void {
        echo "\n".formatToolCall($event->toolCall->name, $event->toolCall->arguments)."\n";
        $toolCallCount++;
    });

    // Create executor
    $executor = new StreamingLlmExecutorWithFunctions(
        provider: $provider,
        prompt: $prompt,
        model: $model,
        tools: $tools,
        maxIterations: 10,
        maxTokens: 1024,
        hooks: $hooks,
    );

    // Stream the response
    echo "\n".BOLD.CYAN.'Assistant: '.RESET;

    $responseText = '';
    $usage = null;
    $startTime = microtime(true);

    try {
        // Get previous messages (excluding current user message for input)
        $previousMessages = array_slice($messages, 0, -1);

        foreach ($executor->stream([
            'message' => $input,
            '_dialogueKey' => 'history',
            'history' => $previousMessages,
        ]) as $event) {
            if ($event instanceof TextDelta) {
                echo $event->text;
                $responseText .= $event->text;
                flush();
            }

            if ($event instanceof ToolCallsReady) {
                // Tool calls count is handled by hook
            }

            if ($event instanceof StreamCompleted) {
                $usage = $event->usage;
            }
        }
    } catch (Throwable $e) {
        echo "\n".RED.'Error: '.$e->getMessage().RESET."\n";
        array_pop($messages);

        continue;
    }

    // Add assistant response to history
    if ($responseText !== '') {
        $messages[] = Message::assistant($responseText);
    }

    $totalTurns++;

    // Show stats
    $duration = round((microtime(true) - $startTime) * 1000);
    echo "\n".DIM."[{$duration}ms";

    if ($usage) {
        echo " | {$usage->inputTokens}+{$usage->outputTokens} tokens";
        $totalInputTokens += $usage->inputTokens;
        $totalOutputTokens += $usage->outputTokens;
    }

    if ($toolCallCount > 0) {
        echo " | {$toolCallCount} tool".($toolCallCount > 1 ? 's' : '');
    }

    echo " | turn {$totalTurns}]".RESET;
}

echo "\n";
