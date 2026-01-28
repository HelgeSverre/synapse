# Agentic Streaming Example Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create a comprehensive agentic CLI demo that showcases multi-turn streaming tool calling with realistic tools, demonstrating the full power of the streaming executor with functions.

**Architecture:** An interactive CLI agent with multiple tools (weather, calculator, file system, web search mock, note-taking) that maintains conversation state, streams responses in real-time, shows tool execution progress, and handles multi-step reasoning. Uses hooks to show timing and token usage.

**Tech Stack:** PHP 8.2+, LlmExe streaming executors, GuzzleStreamTransport, multiple providers (OpenAI/Anthropic/XAI)

---

## Overview

The example will demonstrate:
1. Multi-tool agent with 5+ tools
2. Real-time streaming with tool call progress indicators
3. Multi-turn conversation with memory
4. Tool chaining (agent uses multiple tools in sequence)
5. Hooks for observability (timing, tokens, tool calls)
6. Provider switching via CLI argument
7. Graceful error handling and cancellation support

---

### Task 1: Create Tool Library

**Files:**
- Create: `examples/agentic-tools/WeatherTool.php`
- Create: `examples/agentic-tools/CalculatorTool.php`
- Create: `examples/agentic-tools/NotesTool.php`
- Create: `examples/agentic-tools/WebSearchTool.php`
- Create: `examples/agentic-tools/DateTimeTool.php`

**Step 1: Create WeatherTool**

```php
<?php
// examples/agentic-tools/WeatherTool.php
declare(strict_types=1);

namespace LlmExe\Examples\AgenticTools;

use LlmExe\Executor\CallableExecutor;

final class WeatherTool
{
    public static function create(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'get_weather',
            description: 'Get current weather conditions for a city. Returns temperature, conditions, humidity, and wind speed.',
            handler: function (array $input): string {
                $city = $input['city'] ?? 'Unknown';
                $unit = $input['unit'] ?? 'celsius';
                
                // Simulate API delay
                usleep(200_000);
                
                // Generate realistic-ish mock data based on city name hash
                $hash = crc32(strtolower($city));
                $temp = ($hash % 35) + 5; // 5-40 range
                if ($unit === 'fahrenheit') {
                    $temp = (int) ($temp * 9 / 5 + 32);
                }
                $conditions = ['sunny', 'partly cloudy', 'cloudy', 'light rain', 'thunderstorm', 'snow'][$hash % 6];
                $humidity = ($hash % 60) + 30; // 30-90%
                $wind = ($hash % 30) + 5; // 5-35 km/h
                
                return json_encode([
                    'city' => $city,
                    'temperature' => $temp,
                    'unit' => $unit,
                    'conditions' => $conditions,
                    'humidity' => $humidity . '%',
                    'wind' => $wind . ' km/h',
                ], JSON_THROW_ON_ERROR);
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'city' => [
                        'type' => 'string',
                        'description' => 'City name, e.g., "Oslo", "New York", "Tokyo"',
                    ],
                    'unit' => [
                        'type' => 'string',
                        'enum' => ['celsius', 'fahrenheit'],
                        'description' => 'Temperature unit (default: celsius)',
                    ],
                ],
                'required' => ['city'],
            ],
        );
    }
}
```

**Step 2: Create CalculatorTool**

```php
<?php
// examples/agentic-tools/CalculatorTool.php
declare(strict_types=1);

namespace LlmExe\Examples\AgenticTools;

use LlmExe\Executor\CallableExecutor;

final class CalculatorTool
{
    public static function create(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'calculate',
            description: 'Perform mathematical calculations. Supports basic arithmetic (+, -, *, /), powers (^), parentheses, and common functions (sqrt, sin, cos, tan, log, abs).',
            handler: function (array $input): string {
                $expression = $input['expression'] ?? '';
                
                // Sanitize - only allow safe characters
                $sanitized = preg_replace('/[^0-9+\-*\/().^sqrtincoalg\s]/', '', strtolower($expression));
                
                // Replace common functions and ^ for power
                $sanitized = str_replace('^', '**', $sanitized);
                $sanitized = preg_replace('/sqrt\(/', 'sqrt(', $sanitized);
                
                try {
                    // Use BC math for precision where possible, fall back to eval for complex expressions
                    $result = @eval("return (float)($sanitized);");
                    
                    if ($result === false || !is_numeric($result)) {
                        return json_encode(['error' => 'Invalid expression', 'expression' => $expression]);
                    }
                    
                    // Format result nicely
                    if (floor($result) == $result && abs($result) < PHP_INT_MAX) {
                        $formatted = (string)(int)$result;
                    } else {
                        $formatted = number_format($result, 6, '.', '');
                        $formatted = rtrim(rtrim($formatted, '0'), '.');
                    }
                    
                    return json_encode([
                        'expression' => $expression,
                        'result' => $formatted,
                    ], JSON_THROW_ON_ERROR);
                } catch (\Throwable $e) {
                    return json_encode(['error' => 'Calculation failed: ' . $e->getMessage()]);
                }
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'expression' => [
                        'type' => 'string',
                        'description' => 'Mathematical expression to evaluate, e.g., "2 + 2", "sqrt(16)", "3.14 * 5^2"',
                    ],
                ],
                'required' => ['expression'],
            ],
        );
    }
}
```

**Step 3: Create NotesTool**

```php
<?php
// examples/agentic-tools/NotesTool.php
declare(strict_types=1);

namespace LlmExe\Examples\AgenticTools;

use LlmExe\Executor\CallableExecutor;

final class NotesTool
{
    private static array $notes = [];
    
    public static function create(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'notes',
            description: 'Manage notes. Actions: "add" to save a note, "list" to show all notes, "get" to retrieve a specific note by id, "delete" to remove a note.',
            handler: function (array $input): string {
                $action = $input['action'] ?? 'list';
                $content = $input['content'] ?? '';
                $id = $input['id'] ?? null;
                
                return match ($action) {
                    'add' => self::addNote($content),
                    'list' => self::listNotes(),
                    'get' => self::getNote($id),
                    'delete' => self::deleteNote($id),
                    default => json_encode(['error' => "Unknown action: $action"]),
                };
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['add', 'list', 'get', 'delete'],
                        'description' => 'Action to perform',
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'Note content (required for "add" action)',
                    ],
                    'id' => [
                        'type' => 'integer',
                        'description' => 'Note ID (required for "get" and "delete" actions)',
                    ],
                ],
                'required' => ['action'],
            ],
        );
    }
    
    private static function addNote(string $content): string
    {
        $id = count(self::$notes) + 1;
        self::$notes[$id] = [
            'id' => $id,
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        return json_encode(['success' => true, 'note' => self::$notes[$id]], JSON_THROW_ON_ERROR);
    }
    
    private static function listNotes(): string
    {
        if (empty(self::$notes)) {
            return json_encode(['notes' => [], 'message' => 'No notes yet']);
        }
        return json_encode(['notes' => array_values(self::$notes)], JSON_THROW_ON_ERROR);
    }
    
    private static function getNote(?int $id): string
    {
        if ($id === null || !isset(self::$notes[$id])) {
            return json_encode(['error' => "Note not found: $id"]);
        }
        return json_encode(['note' => self::$notes[$id]], JSON_THROW_ON_ERROR);
    }
    
    private static function deleteNote(?int $id): string
    {
        if ($id === null || !isset(self::$notes[$id])) {
            return json_encode(['error' => "Note not found: $id"]);
        }
        $note = self::$notes[$id];
        unset(self::$notes[$id]);
        return json_encode(['success' => true, 'deleted' => $note], JSON_THROW_ON_ERROR);
    }
    
    public static function reset(): void
    {
        self::$notes = [];
    }
}
```

**Step 4: Create WebSearchTool**

```php
<?php
// examples/agentic-tools/WebSearchTool.php
declare(strict_types=1);

namespace LlmExe\Examples\AgenticTools;

use LlmExe\Executor\CallableExecutor;

final class WebSearchTool
{
    public static function create(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'web_search',
            description: 'Search the web for information. Returns relevant search results with titles, snippets, and URLs.',
            handler: function (array $input): string {
                $query = $input['query'] ?? '';
                $limit = min($input['limit'] ?? 3, 5);
                
                // Simulate search delay
                usleep(300_000);
                
                // Generate mock search results based on query
                $results = [];
                $hash = crc32($query);
                
                $domains = ['wikipedia.org', 'stackoverflow.com', 'github.com', 'medium.com', 'dev.to'];
                $snippetPrefixes = [
                    'Learn everything about',
                    'A comprehensive guide to',
                    'Understanding the basics of',
                    'Expert insights on',
                    'The definitive resource for',
                ];
                
                for ($i = 0; $i < $limit; $i++) {
                    $domain = $domains[($hash + $i) % count($domains)];
                    $prefix = $snippetPrefixes[($hash + $i) % count($snippetPrefixes)];
                    
                    $results[] = [
                        'title' => ucfirst($query) . ' - ' . ucfirst($domain),
                        'url' => "https://$domain/article/" . urlencode(strtolower($query)),
                        'snippet' => "$prefix $query. This article covers key concepts, best practices, and common use cases...",
                    ];
                }
                
                return json_encode([
                    'query' => $query,
                    'results_count' => count($results),
                    'results' => $results,
                ], JSON_THROW_ON_ERROR);
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results (1-5, default: 3)',
                    ],
                ],
                'required' => ['query'],
            ],
        );
    }
}
```

**Step 5: Create DateTimeTool**

```php
<?php
// examples/agentic-tools/DateTimeTool.php
declare(strict_types=1);

namespace LlmExe\Examples\AgenticTools;

use LlmExe\Executor\CallableExecutor;

final class DateTimeTool
{
    public static function create(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'datetime',
            description: 'Get current date/time information, calculate date differences, or format dates.',
            handler: function (array $input): string {
                $action = $input['action'] ?? 'now';
                $timezone = $input['timezone'] ?? 'UTC';
                $date1 = $input['date1'] ?? null;
                $date2 = $input['date2'] ?? null;
                
                try {
                    $tz = new \DateTimeZone($timezone);
                } catch (\Exception) {
                    return json_encode(['error' => "Invalid timezone: $timezone"]);
                }
                
                return match ($action) {
                    'now' => json_encode([
                        'datetime' => (new \DateTime('now', $tz))->format('Y-m-d H:i:s'),
                        'timezone' => $timezone,
                        'day_of_week' => (new \DateTime('now', $tz))->format('l'),
                        'week_number' => (new \DateTime('now', $tz))->format('W'),
                    ]),
                    'diff' => self::calculateDiff($date1, $date2, $tz),
                    'add' => self::addToDate($date1, $input['interval'] ?? 'P1D', $tz),
                    default => json_encode(['error' => "Unknown action: $action"]),
                };
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['now', 'diff', 'add'],
                        'description' => 'Action: "now" for current time, "diff" to calculate difference between dates, "add" to add interval to date',
                    ],
                    'timezone' => [
                        'type' => 'string',
                        'description' => 'Timezone (e.g., "UTC", "Europe/Oslo", "America/New_York")',
                    ],
                    'date1' => [
                        'type' => 'string',
                        'description' => 'First date in Y-m-d format',
                    ],
                    'date2' => [
                        'type' => 'string',
                        'description' => 'Second date in Y-m-d format (for diff action)',
                    ],
                    'interval' => [
                        'type' => 'string',
                        'description' => 'ISO 8601 interval (e.g., "P1D" for 1 day, "P1M" for 1 month)',
                    ],
                ],
                'required' => ['action'],
            ],
        );
    }
    
    private static function calculateDiff(?string $date1, ?string $date2, \DateTimeZone $tz): string
    {
        try {
            $d1 = new \DateTime($date1 ?? 'now', $tz);
            $d2 = new \DateTime($date2 ?? 'now', $tz);
            $diff = $d1->diff($d2);
            
            return json_encode([
                'date1' => $d1->format('Y-m-d'),
                'date2' => $d2->format('Y-m-d'),
                'days' => (int)$diff->format('%r%a'),
                'human' => $diff->format('%y years, %m months, %d days'),
            ], JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            return json_encode(['error' => 'Invalid date format: ' . $e->getMessage()]);
        }
    }
    
    private static function addToDate(?string $date, string $interval, \DateTimeZone $tz): string
    {
        try {
            $d = new \DateTime($date ?? 'now', $tz);
            $d->add(new \DateInterval($interval));
            
            return json_encode([
                'original' => (new \DateTime($date ?? 'now', $tz))->format('Y-m-d'),
                'interval' => $interval,
                'result' => $d->format('Y-m-d'),
                'day_of_week' => $d->format('l'),
            ], JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            return json_encode(['error' => 'Invalid date or interval: ' . $e->getMessage()]);
        }
    }
}
```

**Step 6: Verify files exist**

Run: `ls -la examples/agentic-tools/`

---

### Task 2: Create Main Agent CLI Script

**Files:**
- Create: `examples/agentic-agent-cli.php`

**Step 1: Create the interactive agent CLI**

```php
<?php
// examples/agentic-agent-cli.php
declare(strict_types=1);

/**
 * Agentic Streaming CLI Demo
 *
 * A full-featured AI agent with multiple tools, streaming responses,
 * multi-turn conversation memory, and real-time tool execution display.
 *
 * Usage:
 *   php examples/agentic-agent-cli.php                # Uses OpenAI (default)
 *   php examples/agentic-agent-cli.php anthropic      # Uses Anthropic
 *   php examples/agentic-agent-cli.php xai            # Uses xAI (Grok)
 *
 * Try:
 *   "What's the weather in Tokyo and Oslo? Also, what's 42 * 17?"
 *   "Save a note: Remember to buy groceries"
 *   "What's the current time in New York?"
 *   "Search for information about PHP generators"
 *   "List my notes, then delete the first one"
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/agentic-tools/WeatherTool.php';
require_once __DIR__ . '/agentic-tools/CalculatorTool.php';
require_once __DIR__ . '/agentic-tools/NotesTool.php';
require_once __DIR__ . '/agentic-tools/WebSearchTool.php';
require_once __DIR__ . '/agentic-tools/DateTimeTool.php';

use GuzzleHttp\Client;
use LlmExe\Examples\AgenticTools\CalculatorTool;
use LlmExe\Examples\AgenticTools\DateTimeTool;
use LlmExe\Examples\AgenticTools\NotesTool;
use LlmExe\Examples\AgenticTools\WeatherTool;
use LlmExe\Examples\AgenticTools\WebSearchTool;
use LlmExe\Executor\StreamingLlmExecutorWithFunctions;
use LlmExe\Executor\UseExecutors;
use LlmExe\Hooks\Events\OnStreamChunk;
use LlmExe\Hooks\Events\OnToolCall;
use LlmExe\Hooks\HookDispatcher;
use LlmExe\Prompt\TextPrompt;
use LlmExe\Provider\Anthropic\AnthropicProvider;
use LlmExe\Provider\Http\GuzzleStreamTransport;
use LlmExe\Provider\OpenAI\OpenAIProvider;
use LlmExe\Provider\XAI\XAIProvider;
use LlmExe\State\ConversationState;
use LlmExe\State\Message;
use LlmExe\Streaming\StreamCompleted;
use LlmExe\Streaming\TextDelta;
use LlmExe\Streaming\ToolCallsReady;

// ANSI color codes
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
            new OpenAIProvider($transport, getenv('OPENAI_API_KEY') ?: exit(RED . "OPENAI_API_KEY not set\n" . RESET)),
            'gpt-4o-mini',
            'GPT-4o Mini',
        ],
        'anthropic' => [
            new AnthropicProvider($transport, getenv('ANTHROPIC_API_KEY') ?: exit(RED . "ANTHROPIC_API_KEY not set\n" . RESET)),
            'claude-3-haiku-20240307',
            'Claude 3 Haiku',
        ],
        'xai' => [
            new XAIProvider($transport, getenv('XAI_API_KEY') ?: exit(RED . "XAI_API_KEY not set\n" . RESET)),
            'grok-3-mini-fast',
            'Grok 3 Mini Fast',
        ],
        default => exit(RED . "Unknown provider: {$name}. Use: openai, anthropic, or xai\n" . RESET),
    };
}

function printBanner(string $modelName): void
{
    echo BOLD . CYAN . '
╔═══════════════════════════════════════════════════════════════╗
║              LLM-EXE Agentic Streaming Demo                   ║
╚═══════════════════════════════════════════════════════════════╝' . RESET . '
  Model: ' . GREEN . $modelName . RESET . '
  
  ' . BOLD . 'Available Tools:' . RESET . '
    ' . MAGENTA . '🌤  get_weather' . RESET . '   - Get weather for any city
    ' . MAGENTA . '🔢 calculate' . RESET . '     - Mathematical calculations  
    ' . MAGENTA . '📝 notes' . RESET . '         - Save, list, and manage notes
    ' . MAGENTA . '🔍 web_search' . RESET . '    - Search the web (mock)
    ' . MAGENTA . '🕐 datetime' . RESET . '      - Date/time operations
    
  ' . BOLD . 'Commands:' . RESET . '
    ' . DIM . '/clear' . RESET . '  Clear conversation    ' . DIM . '/tools' . RESET . '  List tools
    ' . DIM . '/stats' . RESET . '  Token usage           ' . DIM . '/exit' . RESET . '   Quit
' . CYAN . '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . RESET . '
';
}

function printToolCall(string $name, array $args): void
{
    $argStr = json_encode($args, JSON_UNESCAPED_UNICODE);
    if (strlen($argStr) > 60) {
        $argStr = substr($argStr, 0, 57) . '...';
    }
    echo "\n  " . MAGENTA . "⚡ " . BOLD . $name . RESET . MAGENTA . "(" . DIM . $argStr . RESET . MAGENTA . ")" . RESET;
}

// Get provider from command line
$providerName = $argv[1] ?? 'openai';
[$provider, $model, $modelDisplayName] = createProvider($providerName);

printBanner($modelDisplayName);

// Create tools
$tools = new UseExecutors([
    WeatherTool::create(),
    CalculatorTool::create(),
    NotesTool::create(),
    WebSearchTool::create(),
    DateTimeTool::create(),
]);

// System prompt for the agent
$systemPrompt = <<<PROMPT
You are a helpful AI assistant with access to several tools. Your capabilities include:

1. **Weather** - Get current weather for any city worldwide
2. **Calculator** - Perform mathematical calculations
3. **Notes** - Save, list, retrieve, and delete personal notes
4. **Web Search** - Search for information on the web
5. **DateTime** - Get current time, calculate date differences, or add intervals

Guidelines:
- Use tools when they would help answer the user's question
- You can use multiple tools in a single response when needed
- Be concise but informative in your responses
- Format numbers and data nicely for readability
- When listing items, use clear formatting

Current date context: Today is {$today}.
PROMPT;

$today = date('l, F j, Y');
$systemPrompt = str_replace('{$today}', $today, $systemPrompt);

// Conversation state
$messages = [Message::system($systemPrompt)];
$totalInputTokens = 0;
$totalOutputTokens = 0;
$totalToolCalls = 0;

// Create prompt that passes through the user input
$prompt = (new TextPrompt())->setContent('{{message}}');

// Main chat loop
while (true) {
    echo "\n" . BOLD . GREEN . 'You: ' . RESET;
    
    $input = trim(fgets(STDIN) ?: '');
    
    if ($input === '') {
        continue;
    }
    
    // Handle commands
    if (str_starts_with($input, '/')) {
        $command = strtolower(trim($input));
        
        if ($command === '/exit' || $command === '/quit') {
            echo DIM . 'Goodbye!' . RESET . "\n";
            break;
        }
        
        if ($command === '/clear') {
            $messages = [Message::system($systemPrompt)];
            NotesTool::reset();
            echo DIM . 'Conversation and notes cleared.' . RESET . "\n";
            continue;
        }
        
        if ($command === '/stats') {
            echo DIM . "Session stats: {$totalInputTokens} input tokens, {$totalOutputTokens} output tokens, {$totalToolCalls} tool calls" . RESET . "\n";
            continue;
        }
        
        if ($command === '/tools') {
            echo "\n" . BOLD . "Available Tools:" . RESET . "\n";
            foreach ($tools->getToolDefinitions() as $tool) {
                echo "  " . MAGENTA . $tool->name . RESET . " - " . DIM . $tool->description . RESET . "\n";
            }
            continue;
        }
        
        if ($command === '/help') {
            echo "\n" . BOLD . "Commands:" . RESET . "\n";
            echo "  /clear  - Clear conversation history\n";
            echo "  /stats  - Show token usage statistics\n";
            echo "  /tools  - List available tools\n";
            echo "  /exit   - Exit the chat\n";
            continue;
        }
        
        echo RED . 'Unknown command. Type /help for available commands.' . RESET . "\n";
        continue;
    }
    
    // Add user message
    $messages[] = Message::user($input);
    
    // Create executor with current state
    $hooks = new HookDispatcher();
    
    // Hook to display tool calls
    $hooks->addListener(OnToolCall::class, function (OnToolCall $event): void {
        printToolCall($event->toolCall->name, $event->toolCall->arguments);
    });
    
    $executor = new StreamingLlmExecutorWithFunctions(
        provider: $provider,
        prompt: $prompt,
        model: $model,
        tools: $tools,
        maxIterations: 10,
        maxTokens: 1024,
        hooks: $hooks,
    );
    
    echo "\n" . BOLD . CYAN . 'Agent: ' . RESET;
    
    $responseText = '';
    $usage = null;
    $toolCallCount = 0;
    $startTime = microtime(true);
    
    try {
        // Build request with message history in the _dialogueKey pattern
        $requestInput = [
            'message' => $input,
            '_dialogueKey' => 'history',
            'history' => array_slice($messages, 0, -1), // All except the just-added user message
        ];
        
        foreach ($executor->stream($requestInput) as $event) {
            if ($event instanceof TextDelta) {
                echo $event->text;
                $responseText .= $event->text;
                flush();
            }
            
            if ($event instanceof ToolCallsReady) {
                $toolCallCount += count($event->toolCalls);
            }
            
            if ($event instanceof StreamCompleted) {
                $usage = $event->usage;
            }
        }
    } catch (\Throwable $e) {
        echo "\n" . RED . 'Error: ' . $e->getMessage() . RESET . "\n";
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
    echo "\n" . DIM . "[{$duration}ms";
    
    if ($usage) {
        echo " | {$usage->inputTokens}+{$usage->outputTokens} tokens";
        $totalInputTokens += $usage->inputTokens;
        $totalOutputTokens += $usage->outputTokens;
    }
    
    if ($toolCallCount > 0) {
        echo " | {$toolCallCount} tool(s)";
        $totalToolCalls += $toolCallCount;
    }
    
    echo ' | ' . (count($messages) - 1) . ' turns]' . RESET;
}

echo "\n";
```

**Step 2: Test the script runs**

Run: `php examples/agentic-agent-cli.php --help 2>&1 || echo "Check if it starts"`

---

### Task 3: Create Unit Tests for Tools

**Files:**
- Create: `tests/Unit/Examples/AgenticToolsTest.php`

**Step 1: Create test file**

```php
<?php
// tests/Unit/Examples/AgenticToolsTest.php
declare(strict_types=1);

namespace LlmExe\Tests\Unit\Examples;

require_once __DIR__ . '/../../../examples/agentic-tools/WeatherTool.php';
require_once __DIR__ . '/../../../examples/agentic-tools/CalculatorTool.php';
require_once __DIR__ . '/../../../examples/agentic-tools/NotesTool.php';
require_once __DIR__ . '/../../../examples/agentic-tools/WebSearchTool.php';
require_once __DIR__ . '/../../../examples/agentic-tools/DateTimeTool.php';

use LlmExe\Examples\AgenticTools\CalculatorTool;
use LlmExe\Examples\AgenticTools\DateTimeTool;
use LlmExe\Examples\AgenticTools\NotesTool;
use LlmExe\Examples\AgenticTools\WeatherTool;
use LlmExe\Examples\AgenticTools\WebSearchTool;
use LlmExe\Executor\CallableExecutor;
use PHPUnit\Framework\TestCase;

final class AgenticToolsTest extends TestCase
{
    protected function setUp(): void
    {
        NotesTool::reset();
    }
    
    public function test_weather_tool_returns_callable_executor(): void
    {
        $tool = WeatherTool::create();
        $this->assertInstanceOf(CallableExecutor::class, $tool);
        $this->assertSame('get_weather', $tool->getName());
    }
    
    public function test_weather_tool_returns_weather_data(): void
    {
        $tool = WeatherTool::create();
        $result = json_decode($tool->execute(['city' => 'Oslo']), true);
        
        $this->assertSame('Oslo', $result['city']);
        $this->assertArrayHasKey('temperature', $result);
        $this->assertArrayHasKey('conditions', $result);
        $this->assertArrayHasKey('humidity', $result);
    }
    
    public function test_calculator_tool_performs_basic_arithmetic(): void
    {
        $tool = CalculatorTool::create();
        
        $result = json_decode($tool->execute(['expression' => '2 + 2']), true);
        $this->assertSame('4', $result['result']);
        
        $result = json_decode($tool->execute(['expression' => '10 * 5']), true);
        $this->assertSame('50', $result['result']);
    }
    
    public function test_notes_tool_add_and_list(): void
    {
        $tool = NotesTool::create();
        
        // Add a note
        $result = json_decode($tool->execute(['action' => 'add', 'content' => 'Test note']), true);
        $this->assertTrue($result['success']);
        $this->assertSame('Test note', $result['note']['content']);
        
        // List notes
        $result = json_decode($tool->execute(['action' => 'list']), true);
        $this->assertCount(1, $result['notes']);
    }
    
    public function test_notes_tool_delete(): void
    {
        $tool = NotesTool::create();
        
        // Add and delete
        $tool->execute(['action' => 'add', 'content' => 'To delete']);
        $result = json_decode($tool->execute(['action' => 'delete', 'id' => 1]), true);
        $this->assertTrue($result['success']);
        
        // Verify empty
        $result = json_decode($tool->execute(['action' => 'list']), true);
        $this->assertEmpty($result['notes']);
    }
    
    public function test_web_search_returns_results(): void
    {
        $tool = WebSearchTool::create();
        $result = json_decode($tool->execute(['query' => 'PHP generators']), true);
        
        $this->assertSame('PHP generators', $result['query']);
        $this->assertGreaterThan(0, $result['results_count']);
        $this->assertArrayHasKey('results', $result);
    }
    
    public function test_datetime_tool_returns_current_time(): void
    {
        $tool = DateTimeTool::create();
        $result = json_decode($tool->execute(['action' => 'now', 'timezone' => 'UTC']), true);
        
        $this->assertArrayHasKey('datetime', $result);
        $this->assertSame('UTC', $result['timezone']);
        $this->assertArrayHasKey('day_of_week', $result);
    }
    
    public function test_datetime_tool_calculates_diff(): void
    {
        $tool = DateTimeTool::create();
        $result = json_decode($tool->execute([
            'action' => 'diff',
            'date1' => '2024-01-01',
            'date2' => '2024-01-10',
        ]), true);
        
        $this->assertSame(9, $result['days']);
    }
}
```

**Step 2: Run tests**

Run: `./vendor/bin/phpunit tests/Unit/Examples/AgenticToolsTest.php --no-coverage`

Expected: All tests pass

---

### Task 4: Verify and Document

**Step 1: Run all unit tests**

Run: `./vendor/bin/phpunit --testsuite=Unit --no-coverage`

**Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/ --no-progress`

**Step 3: Run Pint**

Run: `./vendor/bin/pint`

**Step 4: Manual test the CLI**

Run: `echo "What time is it in Tokyo?" | timeout 30 php examples/agentic-agent-cli.php 2>&1 | head -40`

---

## Execution Options

Plan complete and saved. Two execution options:

**1. Subagent-Driven (this session)** - I dispatch fresh subagent per task, review between tasks, fast iteration

**2. Parallel Session (separate)** - Open new session with executing-plans, batch execution with checkpoints

Which approach?
