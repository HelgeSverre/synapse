# Autonomous Task Decomposer Agent Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create an agent that autonomously decomposes complex goals into subtasks, validates plans as DAGs, executes them in dependency order, tracks state, and re-plans on failure.

**Architecture:** Two-phase approach: (1) Planning phase uses a `submit_plan` tool to force structured JSON output with validation, (2) Execution phase runs each step with a worker executor, tracking completion state. On failure, the planner is re-invoked with context about what failed.

**Tech Stack:** PHP 8.2+, StreamingLlmExecutorWithFunctions, JSON schema validation, topological sort for DAG execution

---

## Task 1: Create Plan Data Structures

**Files:**
- Create: `examples/task-decomposer/Plan.php`
- Create: `examples/task-decomposer/PlanStep.php`
- Create: `examples/task-decomposer/StepStatus.php`

**Step 1: Create StepStatus enum**

```php
<?php
// examples/task-decomposer/StepStatus.php
declare(strict_types=1);

namespace LlmExe\Examples\TaskDecomposer;

enum StepStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
```

**Step 2: Create PlanStep**

```php
<?php
// examples/task-decomposer/PlanStep.php
declare(strict_types=1);

namespace LlmExe\Examples\TaskDecomposer;

final class PlanStep
{
    public StepStatus $status = StepStatus::Pending;
    public ?string $output = null;
    public ?string $error = null;
    public int $attempts = 0;

    /**
     * @param list<string> $dependsOn
     * @param list<string> $tools
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $prompt,
        public readonly array $dependsOn = [],
        public readonly array $tools = [],
    ) {}

    public function markRunning(): void
    {
        $this->status = StepStatus::Running;
        $this->attempts++;
    }

    public function markDone(string $output): void
    {
        $this->status = StepStatus::Done;
        $this->output = $output;
    }

    public function markFailed(string $error): void
    {
        $this->status = StepStatus::Failed;
        $this->error = $error;
    }

    public function canRun(array $completedStepIds): bool
    {
        if ($this->status !== StepStatus::Pending) {
            return false;
        }

        foreach ($this->dependsOn as $dep) {
            if (!in_array($dep, $completedStepIds, true)) {
                return false;
            }
        }

        return true;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status->value,
            'output' => $this->output,
            'error' => $this->error,
            'attempts' => $this->attempts,
        ];
    }
}
```

**Step 3: Create Plan**

```php
<?php
// examples/task-decomposer/Plan.php
declare(strict_types=1);

namespace LlmExe\Examples\TaskDecomposer;

final class Plan
{
    /** @var array<string, PlanStep> */
    private array $steps = [];

    public function __construct(
        public readonly string $goal,
    ) {}

    public function addStep(PlanStep $step): void
    {
        $this->steps[$step->id] = $step;
    }

    public function getStep(string $id): ?PlanStep
    {
        return $this->steps[$id] ?? null;
    }

    /** @return list<PlanStep> */
    public function getSteps(): array
    {
        return array_values($this->steps);
    }

    /** @return list<string> */
    public function getCompletedStepIds(): array
    {
        return array_values(array_map(
            fn(PlanStep $s) => $s->id,
            array_filter($this->steps, fn(PlanStep $s) => $s->status === StepStatus::Done)
        ));
    }

    public function getNextRunnableStep(): ?PlanStep
    {
        $completed = $this->getCompletedStepIds();

        foreach ($this->steps as $step) {
            if ($step->canRun($completed)) {
                return $step;
            }
        }

        return null;
    }

    public function isComplete(): bool
    {
        foreach ($this->steps as $step) {
            if ($step->status !== StepStatus::Done && $step->status !== StepStatus::Skipped) {
                return false;
            }
        }

        return true;
    }

    public function hasFailed(): bool
    {
        foreach ($this->steps as $step) {
            if ($step->status === StepStatus::Failed) {
                return true;
            }
        }

        return false;
    }

    public function getSummary(): string
    {
        $lines = ["Goal: {$this->goal}", "Steps:"];

        foreach ($this->steps as $step) {
            $status = match ($step->status) {
                StepStatus::Done => '✅',
                StepStatus::Failed => '❌',
                StepStatus::Running => '🔄',
                StepStatus::Skipped => '⏭️',
                StepStatus::Pending => '⏳',
            };
            $lines[] = "  {$status} [{$step->id}] {$step->title}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param array{goal: string, steps: list<array{id: string, title: string, prompt: string, depends_on?: list<string>, tools?: list<string>}>} $data
     */
    public static function fromArray(array $data): self
    {
        $plan = new self($data['goal']);

        foreach ($data['steps'] as $stepData) {
            $plan->addStep(new PlanStep(
                id: $stepData['id'],
                title: $stepData['title'],
                prompt: $stepData['prompt'],
                dependsOn: $stepData['depends_on'] ?? [],
                tools: $stepData['tools'] ?? [],
            ));
        }

        return $plan;
    }
}
```

**Step 4: Verify syntax**

Run: `php -l examples/task-decomposer/Plan.php && php -l examples/task-decomposer/PlanStep.php && php -l examples/task-decomposer/StepStatus.php`

---

## Task 2: Create Plan Validator

**Files:**
- Create: `examples/task-decomposer/PlanValidator.php`

**Step 1: Create validator with DAG check**

```php
<?php
// examples/task-decomposer/PlanValidator.php
declare(strict_types=1);

namespace LlmExe\Examples\TaskDecomposer;

final class PlanValidator
{
    private const MAX_STEPS = 20;

    /**
     * Validate a plan structure.
     * 
     * @param array<string, mixed> $data
     * @return array{valid: bool, errors: list<string>, plan: ?Plan}
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Check required fields
        if (!isset($data['goal']) || !is_string($data['goal'])) {
            $errors[] = "Missing or invalid 'goal' field";
        }

        if (!isset($data['steps']) || !is_array($data['steps'])) {
            $errors[] = "Missing or invalid 'steps' field";
            return ['valid' => false, 'errors' => $errors, 'plan' => null];
        }

        if (count($data['steps']) === 0) {
            $errors[] = "Plan must have at least one step";
        }

        if (count($data['steps']) > self::MAX_STEPS) {
            $errors[] = "Plan exceeds maximum of " . self::MAX_STEPS . " steps";
        }

        // Validate steps
        $stepIds = [];
        foreach ($data['steps'] as $i => $step) {
            if (!isset($step['id']) || !is_string($step['id'])) {
                $errors[] = "Step {$i}: missing or invalid 'id'";
                continue;
            }

            if (in_array($step['id'], $stepIds, true)) {
                $errors[] = "Step {$i}: duplicate id '{$step['id']}'";
            }
            $stepIds[] = $step['id'];

            if (!isset($step['title']) || !is_string($step['title'])) {
                $errors[] = "Step {$step['id']}: missing or invalid 'title'";
            }

            if (!isset($step['prompt']) || !is_string($step['prompt'])) {
                $errors[] = "Step {$step['id']}: missing or invalid 'prompt'";
            }

            // Validate depends_on references
            if (isset($step['depends_on']) && is_array($step['depends_on'])) {
                foreach ($step['depends_on'] as $dep) {
                    if (!in_array($dep, $stepIds, true)) {
                        // Forward reference - check if it exists later
                        $allIds = array_column($data['steps'], 'id');
                        if (!in_array($dep, $allIds, true)) {
                            $errors[] = "Step {$step['id']}: depends on unknown step '{$dep}'";
                        }
                    }
                }
            }
        }

        // Check for cycles
        if (empty($errors)) {
            $cycleError = $this->detectCycle($data['steps']);
            if ($cycleError !== null) {
                $errors[] = $cycleError;
            }
        }

        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors, 'plan' => null];
        }

        return [
            'valid' => true,
            'errors' => [],
            'plan' => Plan::fromArray($data),
        ];
    }

    /**
     * Detect cycles in the dependency graph using DFS.
     * 
     * @param list<array{id: string, depends_on?: list<string>}> $steps
     */
    private function detectCycle(array $steps): ?string
    {
        $graph = [];
        foreach ($steps as $step) {
            $graph[$step['id']] = $step['depends_on'] ?? [];
        }

        $visited = [];
        $recStack = [];

        foreach (array_keys($graph) as $node) {
            if ($this->hasCycleDFS($node, $graph, $visited, $recStack)) {
                return "Cycle detected in dependencies involving step '{$node}'";
            }
        }

        return null;
    }

    private function hasCycleDFS(string $node, array $graph, array &$visited, array &$recStack): bool
    {
        if (isset($recStack[$node])) {
            return true; // Back edge = cycle
        }

        if (isset($visited[$node])) {
            return false; // Already processed
        }

        $visited[$node] = true;
        $recStack[$node] = true;

        foreach ($graph[$node] ?? [] as $dep) {
            if ($this->hasCycleDFS($dep, $graph, $visited, $recStack)) {
                return true;
            }
        }

        unset($recStack[$node]);
        return false;
    }
}
```

**Step 2: Verify syntax**

Run: `php -l examples/task-decomposer/PlanValidator.php`

---

## Task 3: Create Submit Plan Tool

**Files:**
- Create: `examples/task-decomposer/SubmitPlanTool.php`

**Step 1: Create the tool**

```php
<?php
// examples/task-decomposer/SubmitPlanTool.php
declare(strict_types=1);

namespace LlmExe\Examples\TaskDecomposer;

use LlmExe\Executor\CallableExecutor;

final class SubmitPlanTool
{
    private ?Plan $lastValidPlan = null;

    public function create(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'submit_plan',
            description: 'Submit a structured execution plan. The plan will be validated for correctness. If invalid, errors will be returned and you should fix and resubmit.',
            handler: function (array $args): string {
                $validator = new PlanValidator();
                $result = $validator->validate($args);

                if (!$result['valid']) {
                    return json_encode([
                        'ok' => false,
                        'errors' => $result['errors'],
                        'hint' => 'Fix the errors and call submit_plan again with corrected plan.',
                    ], JSON_THROW_ON_ERROR);
                }

                $this->lastValidPlan = $result['plan'];

                return json_encode([
                    'ok' => true,
                    'message' => 'Plan validated successfully',
                    'step_count' => count($result['plan']->getSteps()),
                ], JSON_THROW_ON_ERROR);
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'goal' => [
                        'type' => 'string',
                        'description' => 'The high-level goal this plan achieves',
                    ],
                    'steps' => [
                        'type' => 'array',
                        'description' => 'Ordered list of steps to execute',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => [
                                    'type' => 'string',
                                    'description' => 'Unique step identifier (e.g., "s1", "s2")',
                                ],
                                'title' => [
                                    'type' => 'string',
                                    'description' => 'Short title for the step',
                                ],
                                'prompt' => [
                                    'type' => 'string',
                                    'description' => 'Detailed instructions for executing this step',
                                ],
                                'depends_on' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                    'description' => 'IDs of steps that must complete before this one',
                                ],
                                'tools' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                    'description' => 'Names of tools needed for this step',
                                ],
                            ],
                            'required' => ['id', 'title', 'prompt'],
                        ],
                    ],
                ],
                'required' => ['goal', 'steps'],
            ],
        );
    }

    public function getLastValidPlan(): ?Plan
    {
        return $this->lastValidPlan;
    }

    public function reset(): void
    {
        $this->lastValidPlan = null;
    }
}
```

**Step 2: Verify syntax**

Run: `php -l examples/task-decomposer/SubmitPlanTool.php`

---

## Task 4: Create Plan Executor

**Files:**
- Create: `examples/task-decomposer/PlanExecutor.php`

**Step 1: Create the executor**

```php
<?php
// examples/task-decomposer/PlanExecutor.php
declare(strict_types=1);

namespace LlmExe\Examples\TaskDecomposer;

use Generator;
use LlmExe\Executor\StreamingLlmExecutor;
use LlmExe\Prompt\TextPrompt;
use LlmExe\Streaming\StreamableProviderInterface;
use LlmExe\Streaming\StreamEvent;
use LlmExe\Streaming\TextDelta;

/**
 * Executes a plan step by step, yielding progress events.
 */
final class PlanExecutor
{
    private const MAX_STEP_ATTEMPTS = 3;

    public function __construct(
        private readonly StreamableProviderInterface $provider,
        private readonly string $model,
    ) {}

    /**
     * Execute a plan, yielding events for each step.
     * 
     * @return Generator<StepEvent>
     */
    public function execute(Plan $plan): Generator
    {
        yield new StepEvent('plan_started', null, $plan->getSummary());

        while (!$plan->isComplete()) {
            $step = $plan->getNextRunnableStep();

            if ($step === null) {
                if ($plan->hasFailed()) {
                    yield new StepEvent('plan_failed', null, 'Plan has failed steps with no alternatives');
                    return;
                }
                break; // No runnable steps and no failures = deadlock (shouldn't happen with valid DAG)
            }

            yield new StepEvent('step_started', $step->id, "Starting: {$step->title}");
            $step->markRunning();

            try {
                $output = yield from $this->executeStep($step, $plan);
                $step->markDone($output);
                yield new StepEvent('step_completed', $step->id, "Completed: {$step->title}");
            } catch (\Throwable $e) {
                $step->markFailed($e->getMessage());
                yield new StepEvent('step_failed', $step->id, "Failed: {$step->title} - {$e->getMessage()}");

                if ($step->attempts >= self::MAX_STEP_ATTEMPTS) {
                    yield new StepEvent('step_abandoned', $step->id, "Abandoned after {$step->attempts} attempts");
                }
            }
        }

        if ($plan->isComplete()) {
            yield new StepEvent('plan_completed', null, $plan->getSummary());
        }
    }

    /**
     * @return Generator<StreamEvent, mixed, mixed, string>
     */
    private function executeStep(PlanStep $step, Plan $plan): Generator
    {
        // Build context from completed dependencies
        $context = $this->buildStepContext($step, $plan);

        $prompt = (new TextPrompt())->setContent($context);

        $executor = new StreamingLlmExecutor(
            provider: $this->provider,
            prompt: $prompt,
            model: $this->model,
            maxTokens: 1024,
        );

        $output = '';

        foreach ($executor->stream([]) as $event) {
            if ($event instanceof TextDelta) {
                $output .= $event->text;
            }
            yield $event;
        }

        return $output;
    }

    private function buildStepContext(PlanStep $step, Plan $plan): string
    {
        $parts = [
            "You are executing step [{$step->id}] of a larger plan.",
            "",
            "## Your Task",
            $step->prompt,
            "",
        ];

        // Add outputs from dependencies
        $depOutputs = [];
        foreach ($step->dependsOn as $depId) {
            $depStep = $plan->getStep($depId);
            if ($depStep !== null && $depStep->output !== null) {
                $depOutputs[] = "### Output from [{$depId}] {$depStep->title}\n{$depStep->output}";
            }
        }

        if (!empty($depOutputs)) {
            $parts[] = "## Context from Previous Steps";
            $parts = array_merge($parts, $depOutputs);
            $parts[] = "";
        }

        $parts[] = "## Instructions";
        $parts[] = "Complete this step and provide a clear output. Be concise but thorough.";

        return implode("\n", $parts);
    }
}

/**
 * Event emitted during plan execution.
 */
final readonly class StepEvent
{
    public function __construct(
        public string $type, // plan_started, step_started, step_completed, step_failed, plan_completed, plan_failed
        public ?string $stepId,
        public string $message,
    ) {}
}
```

**Step 2: Verify syntax**

Run: `php -l examples/task-decomposer/PlanExecutor.php`

---

## Task 5: Create Main CLI Script

**Files:**
- Create: `examples/task-decomposer-cli.php`

**Step 1: Create the CLI**

```php
<?php
// examples/task-decomposer-cli.php
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

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/task-decomposer/StepStatus.php';
require_once __DIR__ . '/task-decomposer/PlanStep.php';
require_once __DIR__ . '/task-decomposer/Plan.php';
require_once __DIR__ . '/task-decomposer/PlanValidator.php';
require_once __DIR__ . '/task-decomposer/SubmitPlanTool.php';
require_once __DIR__ . '/task-decomposer/PlanExecutor.php';

use GuzzleHttp\Client;
use LlmExe\Examples\TaskDecomposer\PlanExecutor;
use LlmExe\Examples\TaskDecomposer\StepEvent;
use LlmExe\Examples\TaskDecomposer\SubmitPlanTool;
use LlmExe\Executor\StreamingLlmExecutorWithFunctions;
use LlmExe\Executor\UseExecutors;
use LlmExe\Prompt\TextPrompt;
use LlmExe\Provider\Anthropic\AnthropicProvider;
use LlmExe\Provider\Http\GuzzleStreamTransport;
use LlmExe\Provider\OpenAI\OpenAIProvider;
use LlmExe\State\Message;
use LlmExe\Streaming\StreamCompleted;
use LlmExe\Streaming\TextDelta;
use LlmExe\Streaming\ToolCallsReady;

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
            new OpenAIProvider($transport, getenv('OPENAI_API_KEY') ?: exit(RED . "OPENAI_API_KEY not set\n" . RESET)),
            'gpt-4o-mini',
        ],
        'anthropic' => [
            new AnthropicProvider($transport, getenv('ANTHROPIC_API_KEY') ?: exit(RED . "ANTHROPIC_API_KEY not set\n" . RESET)),
            'claude-3-haiku-20240307',
        ],
        default => exit(RED . "Unknown provider: {$name}\n" . RESET),
    };
}

// Banner
echo BOLD . CYAN . "
╔═══════════════════════════════════════════════════════╗
║        Autonomous Task Decomposer Demo                ║
╚═══════════════════════════════════════════════════════╝" . RESET . "

Enter a complex goal and the agent will:
1. " . YELLOW . "Plan" . RESET . " - Break it into subtasks with dependencies
2. " . GREEN . "Validate" . RESET . " - Ensure the plan is a valid DAG
3. " . CYAN . "Execute" . RESET . " - Run each step in order
4. " . MAGENTA . "Track" . RESET . " - Show progress and handle failures

" . BOLD . "Example goals:" . RESET . "
  • \"Write a Python script that fetches weather data and saves it to a file\"
  • \"Create a simple REST API design for a todo app\"
  • \"Outline a blog post about AI agents\"

" . CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . RESET . "
";

// Setup
$providerName = $argv[1] ?? 'openai';
[$provider, $model] = createProvider($providerName);

echo DIM . "Using: {$model}" . RESET . "\n\n";

// Main loop
while (true) {
    echo BOLD . GREEN . "Enter goal (or /exit): " . RESET;
    $goal = trim(fgets(STDIN) ?: '');

    if ($goal === '' || $goal === '/exit') {
        echo DIM . "Goodbye!" . RESET . "\n";
        break;
    }

    // Phase 1: Planning
    echo "\n" . BOLD . YELLOW . "📋 PHASE 1: PLANNING" . RESET . "\n";
    echo DIM . "Creating execution plan..." . RESET . "\n\n";

    $submitPlanTool = new SubmitPlanTool();
    $planningTools = new UseExecutors([$submitPlanTool->create()]);

    $planningPrompt = (new TextPrompt())->setContent(<<<PROMPT
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
                echo DIM . "\n[Validating plan...]" . RESET;
            }
        }
    } catch (\Throwable $e) {
        echo RED . "\nPlanning failed: " . $e->getMessage() . RESET . "\n";
        continue;
    }

    $plan = $submitPlanTool->getLastValidPlan();

    if ($plan === null) {
        echo RED . "\nNo valid plan was created." . RESET . "\n";
        continue;
    }

    echo "\n\n" . BOLD . GREEN . "✅ Plan validated!" . RESET . "\n";
    echo $plan->getSummary() . "\n";

    // Confirm execution
    echo "\n" . BOLD . "Execute this plan? [y/n]: " . RESET;
    $confirm = strtolower(trim(fgets(STDIN) ?: 'n'));

    if ($confirm !== 'y' && $confirm !== 'yes') {
        echo DIM . "Plan cancelled." . RESET . "\n";
        continue;
    }

    // Phase 2: Execution
    echo "\n" . BOLD . CYAN . "🚀 PHASE 2: EXECUTION" . RESET . "\n";

    $planExecutor = new PlanExecutor($provider, $model);

    foreach ($planExecutor->execute($plan) as $event) {
        if ($event instanceof StepEvent) {
            $icon = match ($event->type) {
                'plan_started' => '📋',
                'step_started' => '▶️',
                'step_completed' => '✅',
                'step_failed' => '❌',
                'step_abandoned' => '⛔',
                'plan_completed' => '🎉',
                'plan_failed' => '💥',
                default => '•',
            };

            $color = match ($event->type) {
                'step_completed', 'plan_completed' => GREEN,
                'step_failed', 'step_abandoned', 'plan_failed' => RED,
                'step_started' => YELLOW,
                default => RESET,
            };

            echo "\n" . $color . $icon . " " . $event->message . RESET;

            if ($event->stepId !== null && $event->type === 'step_started') {
                echo "\n" . DIM . str_repeat('─', 40) . RESET . "\n";
            }
        } elseif ($event instanceof TextDelta) {
            echo $event->text;
            flush();
        }
    }

    echo "\n\n" . CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . RESET . "\n";
}
```

**Step 2: Verify syntax**

Run: `php -l examples/task-decomposer-cli.php`

---

## Task 6: Write Tests

**Files:**
- Create: `tests/Unit/Examples/TaskDecomposerTest.php`

**Step 1: Create test file**

```php
<?php
declare(strict_types=1);

namespace LlmExe\Tests\Unit\Examples;

require_once __DIR__ . '/../../../examples/task-decomposer/StepStatus.php';
require_once __DIR__ . '/../../../examples/task-decomposer/PlanStep.php';
require_once __DIR__ . '/../../../examples/task-decomposer/Plan.php';
require_once __DIR__ . '/../../../examples/task-decomposer/PlanValidator.php';
require_once __DIR__ . '/../../../examples/task-decomposer/SubmitPlanTool.php';

use LlmExe\Examples\TaskDecomposer\Plan;
use LlmExe\Examples\TaskDecomposer\PlanStep;
use LlmExe\Examples\TaskDecomposer\PlanValidator;
use LlmExe\Examples\TaskDecomposer\StepStatus;
use LlmExe\Examples\TaskDecomposer\SubmitPlanTool;
use PHPUnit\Framework\TestCase;

final class TaskDecomposerTest extends TestCase
{
    public function test_plan_step_tracks_status(): void
    {
        $step = new PlanStep('s1', 'Test', 'Do something');
        
        $this->assertSame(StepStatus::Pending, $step->status);
        
        $step->markRunning();
        $this->assertSame(StepStatus::Running, $step->status);
        $this->assertSame(1, $step->attempts);
        
        $step->markDone('Result');
        $this->assertSame(StepStatus::Done, $step->status);
        $this->assertSame('Result', $step->output);
    }

    public function test_plan_step_checks_dependencies(): void
    {
        $step = new PlanStep('s2', 'Test', 'Do something', dependsOn: ['s1']);
        
        $this->assertFalse($step->canRun([]));
        $this->assertTrue($step->canRun(['s1']));
    }

    public function test_plan_tracks_completion(): void
    {
        $plan = new Plan('Test goal');
        $plan->addStep(new PlanStep('s1', 'Step 1', 'First'));
        $plan->addStep(new PlanStep('s2', 'Step 2', 'Second', dependsOn: ['s1']));

        $this->assertFalse($plan->isComplete());

        $plan->getStep('s1')->markDone('Done 1');
        $this->assertFalse($plan->isComplete());

        $plan->getStep('s2')->markDone('Done 2');
        $this->assertTrue($plan->isComplete());
    }

    public function test_plan_returns_next_runnable_step(): void
    {
        $plan = new Plan('Test');
        $plan->addStep(new PlanStep('s1', 'First', 'Do first'));
        $plan->addStep(new PlanStep('s2', 'Second', 'Do second', dependsOn: ['s1']));

        $next = $plan->getNextRunnableStep();
        $this->assertSame('s1', $next->id);

        $plan->getStep('s1')->markDone('Done');

        $next = $plan->getNextRunnableStep();
        $this->assertSame('s2', $next->id);
    }

    public function test_validator_accepts_valid_plan(): void
    {
        $validator = new PlanValidator();
        
        $result = $validator->validate([
            'goal' => 'Test goal',
            'steps' => [
                ['id' => 's1', 'title' => 'First', 'prompt' => 'Do first'],
                ['id' => 's2', 'title' => 'Second', 'prompt' => 'Do second', 'depends_on' => ['s1']],
            ],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertInstanceOf(Plan::class, $result['plan']);
    }

    public function test_validator_rejects_missing_goal(): void
    {
        $validator = new PlanValidator();
        
        $result = $validator->validate([
            'steps' => [['id' => 's1', 'title' => 'Test', 'prompt' => 'Do']],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains("Missing or invalid 'goal' field", $result['errors']);
    }

    public function test_validator_rejects_duplicate_ids(): void
    {
        $validator = new PlanValidator();
        
        $result = $validator->validate([
            'goal' => 'Test',
            'steps' => [
                ['id' => 's1', 'title' => 'First', 'prompt' => 'Do'],
                ['id' => 's1', 'title' => 'Duplicate', 'prompt' => 'Do'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            count(array_filter($result['errors'], fn($e) => str_contains($e, 'duplicate'))) > 0
        );
    }

    public function test_validator_rejects_cycles(): void
    {
        $validator = new PlanValidator();
        
        $result = $validator->validate([
            'goal' => 'Test',
            'steps' => [
                ['id' => 's1', 'title' => 'First', 'prompt' => 'Do', 'depends_on' => ['s2']],
                ['id' => 's2', 'title' => 'Second', 'prompt' => 'Do', 'depends_on' => ['s1']],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            count(array_filter($result['errors'], fn($e) => str_contains($e, 'Cycle'))) > 0
        );
    }

    public function test_submit_plan_tool_validates_and_stores(): void
    {
        $submitPlanTool = new SubmitPlanTool();
        $tool = $submitPlanTool->create();

        $result = $tool->execute([
            'goal' => 'Test goal',
            'steps' => [
                ['id' => 's1', 'title' => 'First', 'prompt' => 'Do first'],
            ],
        ]);

        $decoded = json_decode($result, true);
        $this->assertTrue($decoded['ok']);
        $this->assertNotNull($submitPlanTool->getLastValidPlan());
    }

    public function test_submit_plan_tool_returns_errors_for_invalid(): void
    {
        $submitPlanTool = new SubmitPlanTool();
        $tool = $submitPlanTool->create();

        $result = $tool->execute([
            'steps' => [], // Missing goal, empty steps
        ]);

        $decoded = json_decode($result, true);
        $this->assertFalse($decoded['ok']);
        $this->assertNotEmpty($decoded['errors']);
    }
}
```

**Step 2: Run tests**

Run: `./vendor/bin/phpunit tests/Unit/Examples/TaskDecomposerTest.php --no-coverage`

---

## Task 7: Final Verification

**Step 1: Run all unit tests**

Run: `./vendor/bin/phpunit --testsuite=Unit --no-coverage`

**Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/ --no-progress`

**Step 3: Run Pint**

Run: `./vendor/bin/pint`

---

## Execution Options

Plan complete. Two execution options:

**1. Subagent-Driven (this session)** - I dispatch fresh subagent per task, review between tasks, fast iteration

**2. Parallel Session (separate)** - Open new session with executing-plans, batch execution with checkpoints

Which approach?
