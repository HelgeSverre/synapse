<?php

declare(strict_types=1);

/**
 * Human-in-the-Loop Agent Demo
 *
 * Demonstrates an agent that pauses for human approval before executing risky tools.
 *
 * Usage:
 *   php examples/approval-agent-cli.php [provider]
 *
 * Examples:
 *   php examples/approval-agent-cli.php openai
 *   php examples/approval-agent-cli.php anthropic
 */

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/approval-agent/ApprovalRequest.php';
require_once __DIR__.'/approval-agent/ApprovalDecision.php';
require_once __DIR__.'/approval-agent/ApprovalProviderInterface.php';
require_once __DIR__.'/approval-agent/CliApprovalProvider.php';
require_once __DIR__.'/approval-agent/ApprovingUseExecutors.php';
require_once __DIR__.'/approval-agent/RiskyTools.php';

use GuzzleHttp\Client;
use HelgeSverre\Synapse\Examples\ApprovalAgent\ApprovingUseExecutors;
use HelgeSverre\Synapse\Examples\ApprovalAgent\CliApprovalProvider;
use HelgeSverre\Synapse\Examples\ApprovalAgent\RiskyTools;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutorWithFunctions;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\Provider\Anthropic\AnthropicProvider;
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use HelgeSverre\Synapse\Provider\OpenAI\OpenAIProvider;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\TextDelta;

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
            new OpenAIProvider($transport, getenv('OPENAI_API_KEY') ?: exit(RED."OPENAI_API_KEY not set\n".RESET)),
            'gpt-4o-mini',
        ],
        'anthropic' => [
            new AnthropicProvider($transport, getenv('ANTHROPIC_API_KEY') ?: exit(RED."ANTHROPIC_API_KEY not set\n".RESET)),
            'claude-3-haiku-20240307',
        ],
        default => exit(RED."Unknown provider: {$name}\n".RESET),
    };
}

// Banner
echo BOLD.CYAN.'
╔═══════════════════════════════════════════════════════╗
║        Human-in-the-Loop Agent Demo                   ║
╚═══════════════════════════════════════════════════════╝'.RESET.'

This agent will ask for your approval before executing risky actions.

'.BOLD.'Risk Levels:'.RESET.'
  '.DIM.'low'.RESET.'      → No approval needed (read_file)
  '.YELLOW.'medium'.RESET.'   → Approval requested (write_file, send_email)
  '.RED.'high'.RESET.'     → Approval required (delete_files)
  '.RED.BOLD.'critical'.RESET.' → Approval required (execute_command)

'.BOLD.'Commands:'.RESET.' /exit to quit
'.CYAN.'━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━'.RESET.'
';

// Setup
$providerName = $argv[1] ?? 'openai';
[$provider, $model] = createProvider($providerName);

// Create tools with approval wrapper
$baseTools = [
    RiskyTools::readFile(),
    RiskyTools::writeFile(),
    RiskyTools::deleteFiles(),
    RiskyTools::executeCommand(),
    RiskyTools::sendEmail(),
];

$approvalProvider = new CliApprovalProvider;
$tools = new ApprovingUseExecutors($baseTools, $approvalProvider, minimumRiskForApproval: 'medium');

// System prompt
$systemPrompt = "You are a helpful assistant with access to file system and command tools.
You can read, write, and delete files, execute shell commands, and send emails.
Always explain what you're about to do before using a tool.
Available tools: read_file, write_file, delete_files, execute_command, send_email";

$prompt = (new TextPrompt)->setContent('{{message}}');
$messages = [Message::system($systemPrompt)];

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
        maxIterations: 5,
        maxTokens: 1024,
    );

    echo "\n".BOLD.CYAN.'Agent: '.RESET;

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
