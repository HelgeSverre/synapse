<?php

declare(strict_types=1);

/**
 * Multi-Agent Router Demo
 *
 * Demonstrates a manager agent that delegates tasks to specialist agents:
 * - code_reviewer: Reviews code quality
 * - security_auditor: Checks for vulnerabilities
 * - researcher: Researches topics
 * - documenter: Creates documentation
 *
 * Usage:
 *   php examples/router-agent-cli.php [provider]
 *
 * Examples:
 *   php examples/router-agent-cli.php openai
 *   php examples/router-agent-cli.php anthropic
 */

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/router-agent/AgentDefinition.php';
require_once __DIR__.'/router-agent/AgentRegistry.php';
require_once __DIR__.'/router-agent/AgentRunner.php';
require_once __DIR__.'/router-agent/DelegateTool.php';

use GuzzleHttp\Client;
use HelgeSverre\Synapse\Examples\RouterAgent\AgentRegistry;
use HelgeSverre\Synapse\Examples\RouterAgent\AgentStreamEvent;
use HelgeSverre\Synapse\Examples\RouterAgent\DelegateTool;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutorWithFunctions;
use HelgeSverre\Synapse\Executor\ToolRegistry;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\Provider\Anthropic\AnthropicProvider;
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use HelgeSverre\Synapse\Provider\Ollama\OllamaProvider;
use HelgeSverre\Synapse\Provider\OpenAI\OpenAIProvider;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\TextDelta;
use HelgeSverre\Synapse\Streaming\ToolCallsReady;

const CYAN = "\033[36m";
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const RED = "\033[31m";
const MAGENTA = "\033[35m";
const BLUE = "\033[34m";
const RESET = "\033[0m";
const BOLD = "\033[1m";
const DIM = "\033[2m";

function createProvider(string $name): array
{
    $transport = new GuzzleStreamTransport(new Client(['timeout' => 180]));

    return match ($name) {
        'openai' => [
            new OpenAIProvider($transport, getenv('OPENAI_API_KEY') ?: exit(RED."OPENAI_API_KEY not set\n".RESET)),
            'gpt-4o-mini',
        ],
        'anthropic' => [
            new AnthropicProvider($transport, getenv('ANTHROPIC_API_KEY') ?: exit(RED."ANTHROPIC_API_KEY not set\n".RESET)),
            'claude-3-haiku-20240307',
        ],
        'ollama' => [
            new OllamaProvider($transport, getenv('OLLAMA_BASE_URL') ?: 'http://localhost:11434/v1'),
            getenv('OLLAMA_MODEL') ?: 'gemma4:latest',
        ],
        default => exit(RED."Unknown provider: {$name}\n".RESET),
    };
}

// Banner
echo BOLD.CYAN.'
╔═══════════════════════════════════════════════════════╗
║           Multi-Agent Router Demo                     ║
╚═══════════════════════════════════════════════════════╝'.RESET.'

A manager agent that delegates to specialist agents:

'.BOLD.'Available Specialists:'.RESET.'
  '.BLUE.'code_reviewer'.RESET.'    - Reviews code quality and best practices
  '.RED.'security_auditor'.RESET.' - Audits for security vulnerabilities
  '.GREEN.'researcher'.RESET.'       - Researches topics thoroughly
  '.MAGENTA.'documenter'.RESET.'       - Creates documentation

'.BOLD.'Example requests:'.RESET.'
  • "Review this code for bugs and security issues: [paste code]"
  • "Research PHP generators and write documentation for them"
  • "Audit this SQL query for injection vulnerabilities"

'.CYAN.'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━'.RESET.'
';

// Setup
$providerName = $argv[1] ?? 'openai';
[$provider, $model] = createProvider($providerName);

echo DIM."Using: {$model}".RESET."\n\n";

// Create agent registry
$registry = AgentRegistry::withDefaults();

// Agent event callback for real-time display
$onAgentEvent = function (AgentStreamEvent $event): void {
    $color = match ($event->agentName) {
        'code_reviewer' => BLUE,
        'security_auditor' => RED,
        'researcher' => GREEN,
        'documenter' => MAGENTA,
        default => CYAN,
    };

    match ($event->type) {
        'started' => print "\n".$color.BOLD."[{$event->agentName}]".RESET.' '.DIM.$event->content.RESET."\n".str_repeat('-', 50)."\n",
        'delta' => print $event->content,
        'completed' => print "\n".$color."[{$event->agentName}] Complete".RESET."\n",
        'error' => print "\n".RED."[{$event->agentName}] {$event->content}".RESET."\n",
        default => null,
    };

    flush();
};

// Create delegate tool
$delegateTool = new DelegateTool($provider, $model, $registry, $onAgentEvent);
$tools = new ToolRegistry([$delegateTool->create()]);

// Manager system prompt
$managerSystemPrompt = <<<PROMPT
You are a manager agent that coordinates specialist agents to complete tasks.

{$registry->getDescriptionForManager()}

## Your Role
1. Analyze the user's request to understand what needs to be done
2. Decide which specialist(s) to delegate to
3. Use the `delegate` tool to invoke specialists with clear, specific tasks
4. You can delegate to multiple specialists if needed
5. Synthesize the results into a coherent response

## Guidelines
- Break complex requests into specific tasks for each specialist
- If a request needs multiple perspectives (e.g., code review + security), use multiple specialists
- Summarize and combine specialist outputs helpfully
- If no delegation is needed for simple questions, answer directly
PROMPT;

$prompt = (new TextPrompt)->setContent('{{message}}');
$messages = [Message::system($managerSystemPrompt)];

// Main loop
while (true) {
    echo "\n".BOLD.GREEN.'You: '.RESET;
    $input = trim(fgets(STDIN) ?: '');

    if ($input === '' || $input === '/exit') {
        echo DIM.'Goodbye!'.RESET."\n";
        break;
    }

    $messages[] = Message::user($input);

    $executor = new StreamingLlmExecutorWithFunctions(
        provider: $provider,
        prompt: $prompt,
        model: $model,
        tools: $tools,
        maxIterations: 10,
        maxTokens: 2048,
    );

    echo "\n".BOLD.CYAN.'Manager: '.RESET;

    $responseText = '';

    try {
        foreach ($executor->stream([
            'message' => $input,
            '_dialogueKey' => 'history',
            'history' => array_slice($messages, 0, -1),
        ]) as $event) {
            if ($event instanceof TextDelta) {
                echo $event->text;
                $responseText .= $event->text;
                flush();
            }

            if ($event instanceof ToolCallsReady) {
                // Agent events are printed by callback
            }

            if ($event instanceof StreamCompleted) {
                // Done
            }
        }
    } catch (\Throwable $e) {
        echo "\n".RED.'Error: '.$e->getMessage().RESET."\n";
        array_pop($messages);

        continue;
    }

    if ($responseText !== '') {
        $messages[] = Message::assistant($responseText);
    }

    echo "\n";
}
