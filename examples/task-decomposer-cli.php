<?php

declare(strict_types=1);

/**
 * Autonomous Task Decomposer Demo
 *
 * Demonstrates an agent that:
 * 1. Decomposes complex goals into subtasks
 * 2. Validates plans as DAGs
 * 3. Executes steps in dependency order
 * 4. Tracks progress and handles failures
 *
 * Usage:
 *   php examples/task-decomposer-cli.php [provider]
 *
 * Examples:
 *   php examples/task-decomposer-cli.php openai
 *   php examples/task-decomposer-cli.php anthropic
 */

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/task-decomposer/StepStatus.php';
require_once __DIR__.'/task-decomposer/PlanStep.php';
require_once __DIR__.'/task-decomposer/Plan.php';
require_once __DIR__.'/task-decomposer/PlanValidator.php';
require_once __DIR__.'/task-decomposer/SubmitPlanTool.php';
require_once __DIR__.'/task-decomposer/StepEvent.php';
require_once __DIR__.'/task-decomposer/PlanExecutor.php';

use GuzzleHttp\Client;
use HelgeSverre\Synapse\Examples\TaskDecomposer\PlanExecutor;
use HelgeSverre\Synapse\Examples\TaskDecomposer\StepEvent;
use HelgeSverre\Synapse\Examples\TaskDecomposer\SubmitPlanTool;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutorWithFunctions;
use HelgeSverre\Synapse\Executor\ToolRegistry;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\Provider\Anthropic\AnthropicProvider;
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use HelgeSverre\Synapse\Provider\OpenAI\OpenAIProvider;
use HelgeSverre\Synapse\Streaming\TextDelta;
use HelgeSverre\Synapse\Streaming\ToolCallsReady;

const CYAN = "\033[36m";
const GREEN = "\033[32m";
const YELLOW = "\033[33m";
const RED = "\033[31m";
const MAGENTA = "\033[35m";
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
        default => exit(RED."Unknown provider: {$name}\n".RESET),
    };
}

// Banner
echo BOLD.CYAN.'
+-------------------------------------------------------+
|        Autonomous Task Decomposer Demo                |
+-------------------------------------------------------+'.RESET.'

Enter a complex goal and the agent will:
1. '.YELLOW.'Plan'.RESET.' - Break it into subtasks with dependencies
2. '.GREEN.'Validate'.RESET.' - Ensure the plan is a valid DAG
3. '.CYAN.'Execute'.RESET.' - Run each step in order
4. '.MAGENTA.'Track'.RESET.' - Show progress and handle failures

'.BOLD.'Example goals:'.RESET.'
  - "Write a Python script that fetches weather data and saves it to a file"
  - "Create a simple REST API design for a todo app"
  - "Outline a blog post about AI agents"

'.CYAN.'-----------------------------------------------------------'.RESET.'
';

// Setup
$providerName = $argv[1] ?? 'openai';
[$provider, $model] = createProvider($providerName);

echo DIM."Using: {$model}".RESET."\n\n";

// Main loop
while (true) {
    echo BOLD.GREEN.'Enter goal (or /exit): '.RESET;
    $goal = trim(fgets(STDIN) ?: '');

    if ($goal === '' || $goal === '/exit') {
        echo DIM.'Goodbye!'.RESET."\n";
        break;
    }

    // Phase 1: Planning
    echo "\n".BOLD.YELLOW.'PHASE 1: PLANNING'.RESET."\n";
    echo DIM.'Creating execution plan...'.RESET."\n\n";

    $submitPlanTool = new SubmitPlanTool;
    $planningTools = new ToolRegistry([$submitPlanTool->create()]);

    $planningPrompt = (new TextPrompt)->setContent(<<<'PROMPT'
    You are a task planning assistant. Your job is to break down complex goals into clear, executable steps.

    The user's goal is: {{goal}}

    Create a plan by calling the submit_plan tool with:
    - goal: A clear statement of what will be achieved
    - steps: An array of steps, each with:
      - id: Unique identifier (e.g., "s1", "s2")
      - title: Short description
      - prompt: Detailed instructions for executing this step
      - depends_on: Array of step IDs that must complete first (empty array if none)

    Guidelines:
    - Break the goal into 3-7 concrete steps
    - Each step should be independently executable
    - Order steps logically with proper dependencies
    - Be specific in the prompt field
    PROMPT);

    $planningExecutor = new StreamingLlmExecutorWithFunctions(
        provider: $provider,
        prompt: $planningPrompt,
        model: $model,
        tools: $planningTools,
        maxIterations: 5,
        maxTokens: 2048,
    );

    try {
        foreach ($planningExecutor->stream(['goal' => $goal]) as $event) {
            if ($event instanceof TextDelta) {
                echo $event->text;
                flush();
            }
            if ($event instanceof ToolCallsReady) {
                echo DIM."\n[Validating plan...]".RESET;
            }
        }
    } catch (\Throwable $e) {
        echo RED."\nPlanning failed: ".$e->getMessage().RESET."\n";

        continue;
    }

    $plan = $submitPlanTool->getLastValidPlan();

    if ($plan === null) {
        echo RED."\nNo valid plan was created.".RESET."\n";

        continue;
    }

    echo "\n\n".BOLD.GREEN.'Plan validated!'.RESET."\n";
    echo $plan->getSummary()."\n";

    // Confirm execution
    echo "\n".BOLD.'Execute this plan? [y/n]: '.RESET;
    $confirm = strtolower(trim(fgets(STDIN) ?: 'n'));

    if ($confirm !== 'y' && $confirm !== 'yes') {
        echo DIM.'Plan cancelled.'.RESET."\n";

        continue;
    }

    // Phase 2: Execution
    echo "\n".BOLD.CYAN.'PHASE 2: EXECUTION'.RESET."\n";

    $planExecutor = new PlanExecutor($provider, $model);

    foreach ($planExecutor->execute($plan) as $event) {
        if ($event instanceof StepEvent) {
            $marker = match ($event->type) {
                'plan_started' => '[PLAN]',
                'step_started' => '[>>]',
                'step_completed' => '[OK]',
                'step_failed' => '[FAIL]',
                'step_abandoned' => '[STOP]',
                'plan_completed' => '[DONE]',
                'plan_failed' => '[FAIL]',
                default => '[--]',
            };

            $color = match ($event->type) {
                'step_completed', 'plan_completed' => GREEN,
                'step_failed', 'step_abandoned', 'plan_failed' => RED,
                'step_started' => YELLOW,
                default => RESET,
            };

            echo "\n".$color.$marker.' '.$event->message.RESET;

            if ($event->stepId !== null && $event->type === 'step_started') {
                echo "\n".DIM.str_repeat('-', 40).RESET."\n";
            }
        } elseif ($event instanceof TextDelta) {
            echo $event->text;
            flush();
        }
    }

    echo "\n\n".CYAN.'-----------------------------------------------------------'.RESET."\n";
}
