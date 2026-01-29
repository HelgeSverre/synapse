# Multi-Agent Router Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create a manager agent that routes tasks to specialized sub-agents, re-yields their streaming events, and synthesizes results - demonstrating transparent multi-agent orchestration without hidden sub-agents.

**Architecture:** A manager agent has access to a `delegate` tool that invokes specialized executors (CodeReviewAgent, SecurityAgent, ResearchAgent). Each specialist has its own system prompt and toolset. The manager sees full output from each delegation and can combine multiple specialist results.

**Tech Stack:** PHP 8.2+, StreamingLlmExecutorWithFunctions, multiple specialized executors, event re-yielding pattern

---

## Task 1: Create Agent Registry and Specialist Definitions

**Files:**
- Create: `examples/router-agent/AgentDefinition.php`
- Create: `examples/router-agent/AgentRegistry.php`

**Step 1: Create AgentDefinition**

```php
<?php
// examples/router-agent/AgentDefinition.php
declare(strict_types=1);

namespace LlmExe\Examples\RouterAgent;

use LlmExe\Executor\UseExecutors;

/**
 * Definition of a specialist agent.
 */
final readonly class AgentDefinition
{
    /**
     * @param list<string> $capabilities
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $systemPrompt,
        public array $capabilities,
        public ?UseExecutors $tools = null,
    ) {}

    public function toToolDescription(): string
    {
        $caps = implode(', ', $this->capabilities);
        return "{$this->description}. Capabilities: {$caps}";
    }
}
```

**Step 2: Create AgentRegistry**

```php
<?php
// examples/router-agent/AgentRegistry.php
declare(strict_types=1);

namespace LlmExe\Examples\RouterAgent;

use LlmExe\Executor\CallableExecutor;
use LlmExe\Executor\UseExecutors;

/**
 * Registry of available specialist agents.
 */
final class AgentRegistry
{
    /** @var array<string, AgentDefinition> */
    private array $agents = [];

    public function register(AgentDefinition $agent): void
    {
        $this->agents[$agent->name] = $agent;
    }

    public function get(string $name): ?AgentDefinition
    {
        return $this->agents[$name] ?? null;
    }

    /** @return list<AgentDefinition> */
    public function all(): array
    {
        return array_values($this->agents);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->agents);
    }

    public function getDescriptionForManager(): string
    {
        $lines = ["Available specialist agents:"];
        
        foreach ($this->agents as $agent) {
            $lines[] = "- **{$agent->name}**: {$agent->toToolDescription()}";
        }

        return implode("\n", $lines);
    }

    /**
     * Create a registry with default specialist agents.
     */
    public static function withDefaults(): self
    {
        $registry = new self();

        // Code Review Agent
        $registry->register(new AgentDefinition(
            name: 'code_reviewer',
            description: 'Reviews code for quality, best practices, and potential bugs',
            systemPrompt: <<<PROMPT
            You are an expert code reviewer. Analyze the provided code for:
            - Code quality and readability
            - Best practices and design patterns
            - Potential bugs or edge cases
            - Performance considerations
            - Suggestions for improvement
            
            Be constructive and specific in your feedback. Reference line numbers when possible.
            PROMPT,
            capabilities: ['code analysis', 'best practices', 'bug detection', 'refactoring suggestions'],
        ));

        // Security Agent
        $registry->register(new AgentDefinition(
            name: 'security_auditor',
            description: 'Audits code and systems for security vulnerabilities',
            systemPrompt: <<<PROMPT
            You are a security expert. Analyze the provided content for:
            - Common vulnerabilities (OWASP Top 10)
            - Injection risks (SQL, XSS, command injection)
            - Authentication and authorization issues
            - Data exposure risks
            - Insecure configurations
            
            Rate each finding by severity (Critical, High, Medium, Low, Info).
            Provide specific remediation steps.
            PROMPT,
            capabilities: ['vulnerability scanning', 'OWASP analysis', 'security recommendations'],
        ));

        // Research Agent
        $registry->register(new AgentDefinition(
            name: 'researcher',
            description: 'Researches topics and provides comprehensive summaries',
            systemPrompt: <<<PROMPT
            You are a research assistant. When given a topic:
            - Provide a comprehensive overview
            - Explain key concepts clearly
            - Include relevant examples
            - Cite sources when applicable
            - Organize information logically
            
            Be thorough but concise. Focus on accuracy.
            PROMPT,
            capabilities: ['topic research', 'summarization', 'explanation', 'examples'],
        ));

        // Documentation Agent
        $registry->register(new AgentDefinition(
            name: 'documenter',
            description: 'Creates or improves documentation for code and APIs',
            systemPrompt: <<<PROMPT
            You are a technical writer. When documenting:
            - Write clear, concise explanations
            - Include usage examples
            - Document parameters, return types, and exceptions
            - Follow standard documentation formats (JSDoc, PHPDoc, etc.)
            - Consider the target audience (developers)
            
            Good documentation is accurate, complete, and easy to understand.
            PROMPT,
            capabilities: ['API docs', 'code comments', 'README writing', 'tutorials'],
        ));

        return $registry;
    }
}
```

**Step 3: Verify syntax**

Run: `php -l examples/router-agent/AgentDefinition.php && php -l examples/router-agent/AgentRegistry.php`

---

## Task 2: Create Delegation Tool and Agent Runner

**Files:**
- Create: `examples/router-agent/DelegateTool.php`
- Create: `examples/router-agent/AgentRunner.php`

**Step 1: Create AgentRunner**

```php
<?php
// examples/router-agent/AgentRunner.php
declare(strict_types=1);

namespace LlmExe\Examples\RouterAgent;

use Generator;
use LlmExe\Executor\StreamingLlmExecutor;
use LlmExe\Executor\StreamingLlmExecutorWithFunctions;
use LlmExe\Prompt\TextPrompt;
use LlmExe\Streaming\StreamableProviderInterface;
use LlmExe\Streaming\StreamEvent;
use LlmExe\Streaming\TextDelta;

/**
 * Runs a specialist agent and collects/yields results.
 */
final class AgentRunner
{
    public function __construct(
        private readonly StreamableProviderInterface $provider,
        private readonly string $model,
        private readonly AgentRegistry $registry,
    ) {}

    /**
     * Run a specialist agent with the given task.
     * 
     * @return Generator<AgentStreamEvent>
     */
    public function run(string $agentName, string $task): Generator
    {
        $agent = $this->registry->get($agentName);

        if ($agent === null) {
            yield new AgentStreamEvent(
                type: 'error',
                agentName: $agentName,
                content: "Unknown agent: {$agentName}",
            );
            return;
        }

        yield new AgentStreamEvent(
            type: 'started',
            agentName: $agentName,
            content: "Delegating to {$agentName}...",
        );

        $prompt = (new TextPrompt())->setContent($agent->systemPrompt . "\n\n## Task\n{{task}}");

        $executor = $agent->tools !== null
            ? new StreamingLlmExecutorWithFunctions(
                provider: $this->provider,
                prompt: $prompt,
                model: $this->model,
                tools: $agent->tools,
                maxIterations: 5,
                maxTokens: 2048,
            )
            : new StreamingLlmExecutor(
                provider: $this->provider,
                prompt: $prompt,
                model: $this->model,
                maxTokens: 2048,
            );

        $fullOutput = '';

        try {
            foreach ($executor->stream(['task' => $task]) as $event) {
                if ($event instanceof TextDelta) {
                    $fullOutput .= $event->text;
                    yield new AgentStreamEvent(
                        type: 'delta',
                        agentName: $agentName,
                        content: $event->text,
                    );
                }
            }

            yield new AgentStreamEvent(
                type: 'completed',
                agentName: $agentName,
                content: $fullOutput,
            );
        } catch (\Throwable $e) {
            yield new AgentStreamEvent(
                type: 'error',
                agentName: $agentName,
                content: "Agent error: {$e->getMessage()}",
            );
        }
    }
}

/**
 * Event from a running agent.
 */
final readonly class AgentStreamEvent
{
    public function __construct(
        public string $type, // started, delta, completed, error
        public string $agentName,
        public string $content,
    ) {}
}
```

**Step 2: Create DelegateTool**

```php
<?php
// examples/router-agent/DelegateTool.php
declare(strict_types=1);

namespace LlmExe\Examples\RouterAgent;

use LlmExe\Executor\CallableExecutor;
use LlmExe\Streaming\StreamableProviderInterface;

/**
 * Creates a delegate tool that the manager can use to invoke specialist agents.
 * 
 * NOTE: This is a synchronous version for simplicity.
 * The streaming version requires yielding from within tool execution,
 * which would need executor changes.
 */
final class DelegateTool
{
    private array $lastResults = [];

    public function __construct(
        private readonly StreamableProviderInterface $provider,
        private readonly string $model,
        private readonly AgentRegistry $registry,
        private readonly ?\Closure $onAgentEvent = null,
    ) {}

    public function create(): CallableExecutor
    {
        $agentNames = $this->registry->names();
        $agentDescriptions = [];
        
        foreach ($this->registry->all() as $agent) {
            $agentDescriptions[$agent->name] = $agent->toToolDescription();
        }

        return new CallableExecutor(
            name: 'delegate',
            description: 'Delegate a task to a specialist agent. Available agents: ' . implode(', ', $agentNames),
            handler: function (array $args) use ($agentDescriptions): string {
                $agentName = $args['agent'] ?? '';
                $task = $args['task'] ?? '';

                if (!isset($agentDescriptions[$agentName])) {
                    return json_encode([
                        'error' => "Unknown agent: {$agentName}",
                        'available_agents' => array_keys($agentDescriptions),
                    ]);
                }

                $runner = new AgentRunner($this->provider, $this->model, $this->registry);
                $output = '';

                foreach ($runner->run($agentName, $task) as $event) {
                    if ($this->onAgentEvent !== null) {
                        ($this->onAgentEvent)($event);
                    }

                    if ($event->type === 'completed') {
                        $output = $event->content;
                    } elseif ($event->type === 'error') {
                        return json_encode(['error' => $event->content]);
                    }
                }

                $this->lastResults[$agentName] = $output;

                return json_encode([
                    'agent' => $agentName,
                    'output' => $output,
                ]);
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'agent' => [
                        'type' => 'string',
                        'enum' => $agentNames,
                        'description' => 'Name of the specialist agent to delegate to',
                    ],
                    'task' => [
                        'type' => 'string',
                        'description' => 'The task or question for the specialist agent',
                    ],
                ],
                'required' => ['agent', 'task'],
            ],
        );
    }

    /** @return array<string, string> */
    public function getLastResults(): array
    {
        return $this->lastResults;
    }
}
```

**Step 3: Verify syntax**

Run: `php -l examples/router-agent/AgentRunner.php && php -l examples/router-agent/DelegateTool.php`

---

## Task 3: Create Main CLI Script

**Files:**
- Create: `examples/router-agent-cli.php`

**Step 1: Create the CLI**

```php
<?php
// examples/router-agent-cli.php
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

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/router-agent/AgentDefinition.php';
require_once __DIR__ . '/router-agent/AgentRegistry.php';
require_once __DIR__ . '/router-agent/AgentRunner.php';
require_once __DIR__ . '/router-agent/DelegateTool.php';

use GuzzleHttp\Client;
use LlmExe\Examples\RouterAgent\AgentRegistry;
use LlmExe\Examples\RouterAgent\AgentStreamEvent;
use LlmExe\Examples\RouterAgent\DelegateTool;
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
const BLUE = "\033[34m";
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
║           Multi-Agent Router Demo                     ║
╚═══════════════════════════════════════════════════════╝" . RESET . "

A manager agent that delegates to specialist agents:

" . BOLD . "Available Specialists:" . RESET . "
  " . BLUE . "code_reviewer" . RESET . "    - Reviews code quality and best practices
  " . RED . "security_auditor" . RESET . " - Audits for security vulnerabilities
  " . GREEN . "researcher" . RESET . "       - Researches topics thoroughly
  " . MAGENTA . "documenter" . RESET . "       - Creates documentation

" . BOLD . "Example requests:" . RESET . "
  • \"Review this code for bugs and security issues: [paste code]\"
  • \"Research PHP generators and write documentation for them\"
  • \"Audit this SQL query for injection vulnerabilities\"

" . CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . RESET . "
";

// Setup
$providerName = $argv[1] ?? 'openai';
[$provider, $model] = createProvider($providerName);

echo DIM . "Using: {$model}" . RESET . "\n\n";

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
        'started' => print("\n" . $color . BOLD . "🤖 [{$event->agentName}]" . RESET . " " . DIM . $event->content . RESET . "\n" . str_repeat('─', 50) . "\n"),
        'delta' => print($event->content),
        'completed' => print("\n" . $color . "✅ [{$event->agentName}] Complete" . RESET . "\n"),
        'error' => print("\n" . RED . "❌ [{$event->agentName}] {$event->content}" . RESET . "\n"),
        default => null,
    };

    flush();
};

// Create delegate tool
$delegateTool = new DelegateTool($provider, $model, $registry, $onAgentEvent);
$tools = new UseExecutors([$delegateTool->create()]);

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

$prompt = (new TextPrompt())->setContent('{{message}}');
$messages = [Message::system($managerSystemPrompt)];

// Main loop
while (true) {
    echo "\n" . BOLD . GREEN . "You: " . RESET;
    $input = trim(fgets(STDIN) ?: '');

    if ($input === '' || $input === '/exit') {
        echo DIM . "Goodbye!" . RESET . "\n";
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

    echo "\n" . BOLD . CYAN . "Manager: " . RESET;

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
        echo "\n" . RED . "Error: " . $e->getMessage() . RESET . "\n";
        array_pop($messages);
        continue;
    }

    if ($responseText !== '') {
        $messages[] = Message::assistant($responseText);
    }

    echo "\n";
}
```

**Step 2: Verify syntax**

Run: `php -l examples/router-agent-cli.php`

---

## Task 4: Write Tests

**Files:**
- Create: `tests/Unit/Examples/RouterAgentTest.php`

**Step 1: Create test file**

```php
<?php
declare(strict_types=1);

namespace LlmExe\Tests\Unit\Examples;

require_once __DIR__ . '/../../../examples/router-agent/AgentDefinition.php';
require_once __DIR__ . '/../../../examples/router-agent/AgentRegistry.php';

use LlmExe\Examples\RouterAgent\AgentDefinition;
use LlmExe\Examples\RouterAgent\AgentRegistry;
use PHPUnit\Framework\TestCase;

final class RouterAgentTest extends TestCase
{
    public function test_agent_definition_creates_tool_description(): void
    {
        $agent = new AgentDefinition(
            name: 'test_agent',
            description: 'A test agent',
            systemPrompt: 'You are a test.',
            capabilities: ['testing', 'mocking'],
        );

        $desc = $agent->toToolDescription();
        
        $this->assertStringContainsString('A test agent', $desc);
        $this->assertStringContainsString('testing', $desc);
        $this->assertStringContainsString('mocking', $desc);
    }

    public function test_registry_registers_and_retrieves_agents(): void
    {
        $registry = new AgentRegistry();
        
        $agent = new AgentDefinition(
            name: 'my_agent',
            description: 'Test',
            systemPrompt: 'Test',
            capabilities: ['test'],
        );

        $registry->register($agent);

        $this->assertSame($agent, $registry->get('my_agent'));
        $this->assertNull($registry->get('unknown'));
    }

    public function test_registry_lists_agent_names(): void
    {
        $registry = new AgentRegistry();
        
        $registry->register(new AgentDefinition('agent1', 'Desc', 'Prompt', ['cap']));
        $registry->register(new AgentDefinition('agent2', 'Desc', 'Prompt', ['cap']));

        $names = $registry->names();

        $this->assertContains('agent1', $names);
        $this->assertContains('agent2', $names);
    }

    public function test_default_registry_has_specialists(): void
    {
        $registry = AgentRegistry::withDefaults();

        $names = $registry->names();

        $this->assertContains('code_reviewer', $names);
        $this->assertContains('security_auditor', $names);
        $this->assertContains('researcher', $names);
        $this->assertContains('documenter', $names);
    }

    public function test_registry_generates_manager_description(): void
    {
        $registry = AgentRegistry::withDefaults();
        
        $desc = $registry->getDescriptionForManager();

        $this->assertStringContainsString('Available specialist agents', $desc);
        $this->assertStringContainsString('code_reviewer', $desc);
        $this->assertStringContainsString('security_auditor', $desc);
    }
}
```

**Step 2: Run tests**

Run: `./vendor/bin/phpunit tests/Unit/Examples/RouterAgentTest.php --no-coverage`

---

## Task 5: Final Verification

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
