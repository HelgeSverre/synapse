# Human-in-the-Loop Agent Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create an agent that pauses for human approval before executing risky tools, demonstrating the human-in-the-loop pattern with approve/reject/edit decisions.

**Architecture:** A decorator (`ApprovingUseExecutors`) wraps the tool executor and intercepts calls to risky tools. Risk is determined by tool attributes. When a risky tool is called, execution pauses, prompts the human for a decision, and either proceeds, rejects with feedback, or modifies arguments before execution.

**Tech Stack:** PHP 8.2+, existing StreamingLlmExecutorWithFunctions, CallableExecutor with attributes, CLI-based approval provider

---

## Task 1: Create Approval Value Objects

**Files:**
- Create: `examples/approval-agent/ApprovalRequest.php`
- Create: `examples/approval-agent/ApprovalDecision.php`

**Step 1: Create ApprovalRequest**

```php
<?php
// examples/approval-agent/ApprovalRequest.php
declare(strict_types=1);

namespace LlmExe\Examples\ApprovalAgent;

/**
 * Represents a request for human approval before tool execution.
 */
final readonly class ApprovalRequest
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public string $toolName,
        public array $arguments,
        public string $riskLevel, // 'low', 'medium', 'high', 'critical'
        public string $description,
    ) {}

    public function format(): string
    {
        $argsJson = json_encode($this->arguments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return <<<TEXT
        Tool: {$this->toolName}
        Risk: {$this->riskLevel}
        Description: {$this->description}
        Arguments:
        {$argsJson}
        TEXT;
    }
}
```

**Step 2: Create ApprovalDecision**

```php
<?php
// examples/approval-agent/ApprovalDecision.php
declare(strict_types=1);

namespace LlmExe\Examples\ApprovalAgent;

enum ApprovalAction: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case Edit = 'edit';
}

/**
 * Represents the human's decision on an approval request.
 */
final readonly class ApprovalDecision
{
    /**
     * @param array<string, mixed>|null $editedArguments
     */
    public function __construct(
        public ApprovalAction $action,
        public ?string $reason = null,
        public ?array $editedArguments = null,
    ) {}

    public static function approve(): self
    {
        return new self(ApprovalAction::Approve);
    }

    public static function reject(string $reason): self
    {
        return new self(ApprovalAction::Reject, $reason);
    }

    /**
     * @param array<string, mixed> $newArguments
     */
    public static function edit(array $newArguments): self
    {
        return new self(ApprovalAction::Edit, null, $newArguments);
    }
}
```

**Step 3: Verify syntax**

Run: `php -l examples/approval-agent/ApprovalRequest.php && php -l examples/approval-agent/ApprovalDecision.php`

---

## Task 2: Create Approval Provider Interface and CLI Implementation

**Files:**
- Create: `examples/approval-agent/ApprovalProviderInterface.php`
- Create: `examples/approval-agent/CliApprovalProvider.php`

**Step 1: Create interface**

```php
<?php
// examples/approval-agent/ApprovalProviderInterface.php
declare(strict_types=1);

namespace LlmExe\Examples\ApprovalAgent;

/**
 * Interface for requesting human approval.
 * Implementations may be CLI-based, web-based, or queue-based.
 */
interface ApprovalProviderInterface
{
    /**
     * Request approval from a human. Blocks until decision is made.
     */
    public function requestApproval(ApprovalRequest $request): ApprovalDecision;
}
```

**Step 2: Create CLI implementation**

```php
<?php
// examples/approval-agent/CliApprovalProvider.php
declare(strict_types=1);

namespace LlmExe\Examples\ApprovalAgent;

/**
 * CLI-based approval provider that prompts via STDIN.
 */
final class CliApprovalProvider implements ApprovalProviderInterface
{
    private const YELLOW = "\033[33m";
    private const RED = "\033[31m";
    private const GREEN = "\033[32m";
    private const CYAN = "\033[36m";
    private const RESET = "\033[0m";
    private const BOLD = "\033[1m";

    public function requestApproval(ApprovalRequest $request): ApprovalDecision
    {
        $riskColor = match ($request->riskLevel) {
            'critical' => self::RED,
            'high' => self::RED,
            'medium' => self::YELLOW,
            default => self::CYAN,
        };

        echo "\n" . self::BOLD . $riskColor . "⚠️  APPROVAL REQUIRED" . self::RESET . "\n";
        echo str_repeat('─', 50) . "\n";
        echo self::CYAN . "Tool: " . self::RESET . $request->toolName . "\n";
        echo self::CYAN . "Risk: " . self::RESET . $riskColor . strtoupper($request->riskLevel) . self::RESET . "\n";
        echo self::CYAN . "Description: " . self::RESET . $request->description . "\n";
        echo self::CYAN . "Arguments: " . self::RESET . "\n";
        
        $argsJson = json_encode($request->arguments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        foreach (explode("\n", $argsJson) as $line) {
            echo "  " . $line . "\n";
        }
        
        echo str_repeat('─', 50) . "\n";
        echo self::BOLD . "[y]" . self::RESET . " Approve  ";
        echo self::BOLD . "[n]" . self::RESET . " Reject  ";
        echo self::BOLD . "[e]" . self::RESET . " Edit\n";
        echo "> ";

        $input = strtolower(trim(fgets(STDIN) ?: 'n'));

        return match ($input) {
            'y', 'yes' => ApprovalDecision::approve(),
            'e', 'edit' => $this->handleEdit($request),
            default => $this->handleReject(),
        };
    }

    private function handleReject(): ApprovalDecision
    {
        echo "Reason for rejection (optional): ";
        $reason = trim(fgets(STDIN) ?: '');
        return ApprovalDecision::reject($reason ?: 'User rejected the action');
    }

    private function handleEdit(ApprovalRequest $request): ApprovalDecision
    {
        echo "Enter new arguments as JSON (or press Enter to cancel):\n";
        $json = trim(fgets(STDIN) ?: '');
        
        if ($json === '') {
            return ApprovalDecision::reject('User cancelled edit');
        }

        $newArgs = json_decode($json, true);
        if (!is_array($newArgs)) {
            echo self::RED . "Invalid JSON. Rejecting." . self::RESET . "\n";
            return ApprovalDecision::reject('Invalid JSON provided for edit');
        }

        return ApprovalDecision::edit($newArgs);
    }
}
```

**Step 3: Verify syntax**

Run: `php -l examples/approval-agent/CliApprovalProvider.php`

---

## Task 3: Create ApprovingUseExecutors Decorator

**Files:**
- Create: `examples/approval-agent/ApprovingUseExecutors.php`

**Step 1: Create the decorator**

```php
<?php
// examples/approval-agent/ApprovingUseExecutors.php
declare(strict_types=1);

namespace LlmExe\Examples\ApprovalAgent;

use LlmExe\Executor\UseExecutors;
use LlmExe\Provider\Request\ToolDefinition;

/**
 * Decorator that intercepts tool calls and requires approval for risky tools.
 * 
 * Risk is determined by tool attributes:
 * - No 'risk' attribute or 'risk' => 'low': no approval needed
 * - 'risk' => 'medium': approval requested
 * - 'risk' => 'high' or 'critical': approval required
 */
final class ApprovingUseExecutors extends UseExecutors
{
    /** @var array<string, string> Tool name => risk level */
    private array $riskLevels = [];

    /** @var array<string, string> Tool name => description */
    private array $descriptions = [];

    public function __construct(
        array $executors,
        private readonly ApprovalProviderInterface $approvalProvider,
        private readonly string $minimumRiskForApproval = 'medium',
    ) {
        parent::__construct($executors);

        // Extract risk levels from executor attributes
        foreach ($executors as $executor) {
            $name = $executor->getName();
            $attrs = method_exists($executor, 'getAttributes') ? $executor->getAttributes() : [];
            $this->riskLevels[$name] = $attrs['risk'] ?? 'low';
            $this->descriptions[$name] = $attrs['description'] ?? $executor->getDescription();
        }
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function callFunction(string $name, array $arguments): mixed
    {
        $riskLevel = $this->riskLevels[$name] ?? 'low';

        if ($this->requiresApproval($riskLevel)) {
            $request = new ApprovalRequest(
                toolName: $name,
                arguments: $arguments,
                riskLevel: $riskLevel,
                description: $this->descriptions[$name] ?? '',
            );

            $decision = $this->approvalProvider->requestApproval($request);

            return match ($decision->action) {
                ApprovalAction::Approve => parent::callFunction($name, $arguments),
                ApprovalAction::Edit => parent::callFunction($name, $decision->editedArguments ?? $arguments),
                ApprovalAction::Reject => json_encode([
                    'error' => 'Action rejected by user',
                    'reason' => $decision->reason,
                    'tool' => $name,
                ]),
            };
        }

        return parent::callFunction($name, $arguments);
    }

    private function requiresApproval(string $riskLevel): bool
    {
        $levels = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $toolLevel = $levels[$riskLevel] ?? 1;
        $minLevel = $levels[$this->minimumRiskForApproval] ?? 2;

        return $toolLevel >= $minLevel;
    }
}
```

**Step 2: Verify syntax**

Run: `php -l examples/approval-agent/ApprovingUseExecutors.php`

---

## Task 4: Create Example Tools with Risk Attributes

**Files:**
- Create: `examples/approval-agent/RiskyTools.php`

**Step 1: Create risky tools**

```php
<?php
// examples/approval-agent/RiskyTools.php
declare(strict_types=1);

namespace LlmExe\Examples\ApprovalAgent;

use LlmExe\Executor\CallableExecutor;

final class RiskyTools
{
    /**
     * Safe tool - no approval needed
     */
    public static function readFile(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'read_file',
            description: 'Read contents of a file',
            handler: fn(array $args) => json_encode([
                'path' => $args['path'] ?? '',
                'content' => '[Mock file content for: ' . ($args['path'] ?? 'unknown') . ']',
            ]),
            parameters: [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'File path to read'],
                ],
                'required' => ['path'],
            ],
            attributes: ['risk' => 'low'],
        );
    }

    /**
     * Medium risk - file modification
     */
    public static function writeFile(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'write_file',
            description: 'Write content to a file (overwrites existing)',
            handler: fn(array $args) => json_encode([
                'success' => true,
                'path' => $args['path'] ?? '',
                'bytes_written' => strlen($args['content'] ?? ''),
            ]),
            parameters: [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'File path to write'],
                    'content' => ['type' => 'string', 'description' => 'Content to write'],
                ],
                'required' => ['path', 'content'],
            ],
            attributes: ['risk' => 'medium', 'description' => 'Overwrites file contents'],
        );
    }

    /**
     * High risk - file deletion
     */
    public static function deleteFiles(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'delete_files',
            description: 'Delete files matching a pattern',
            handler: fn(array $args) => json_encode([
                'success' => true,
                'pattern' => $args['pattern'] ?? '',
                'files_deleted' => rand(1, 50), // Mock
            ]),
            parameters: [
                'type' => 'object',
                'properties' => [
                    'pattern' => ['type' => 'string', 'description' => 'Glob pattern for files to delete'],
                ],
                'required' => ['pattern'],
            ],
            attributes: ['risk' => 'high', 'description' => 'Permanently deletes files'],
        );
    }

    /**
     * Critical risk - execute shell command
     */
    public static function executeCommand(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'execute_command',
            description: 'Execute a shell command',
            handler: fn(array $args) => json_encode([
                'command' => $args['command'] ?? '',
                'output' => '[Mock output for: ' . ($args['command'] ?? '') . ']',
                'exit_code' => 0,
            ]),
            parameters: [
                'type' => 'object',
                'properties' => [
                    'command' => ['type' => 'string', 'description' => 'Shell command to execute'],
                ],
                'required' => ['command'],
            ],
            attributes: ['risk' => 'critical', 'description' => 'Executes arbitrary shell commands'],
        );
    }

    /**
     * Medium risk - send email
     */
    public static function sendEmail(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'send_email',
            description: 'Send an email',
            handler: fn(array $args) => json_encode([
                'success' => true,
                'to' => $args['to'] ?? '',
                'subject' => $args['subject'] ?? '',
                'message_id' => uniqid('msg_'),
            ]),
            parameters: [
                'type' => 'object',
                'properties' => [
                    'to' => ['type' => 'string', 'description' => 'Recipient email'],
                    'subject' => ['type' => 'string', 'description' => 'Email subject'],
                    'body' => ['type' => 'string', 'description' => 'Email body'],
                ],
                'required' => ['to', 'subject', 'body'],
            ],
            attributes: ['risk' => 'medium', 'description' => 'Sends email to external recipient'],
        );
    }
}
```

**Step 2: Verify syntax**

Run: `php -l examples/approval-agent/RiskyTools.php`

---

## Task 5: Create the Main CLI Script

**Files:**
- Create: `examples/approval-agent-cli.php`

**Step 1: Create the CLI**

```php
<?php
// examples/approval-agent-cli.php
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

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/approval-agent/ApprovalRequest.php';
require_once __DIR__ . '/approval-agent/ApprovalDecision.php';
require_once __DIR__ . '/approval-agent/ApprovalProviderInterface.php';
require_once __DIR__ . '/approval-agent/CliApprovalProvider.php';
require_once __DIR__ . '/approval-agent/ApprovingUseExecutors.php';
require_once __DIR__ . '/approval-agent/RiskyTools.php';

use GuzzleHttp\Client;
use LlmExe\Examples\ApprovalAgent\ApprovingUseExecutors;
use LlmExe\Examples\ApprovalAgent\CliApprovalProvider;
use LlmExe\Examples\ApprovalAgent\RiskyTools;
use LlmExe\Executor\StreamingLlmExecutorWithFunctions;
use LlmExe\Prompt\TextPrompt;
use LlmExe\Provider\Anthropic\AnthropicProvider;
use LlmExe\Provider\Http\GuzzleStreamTransport;
use LlmExe\Provider\OpenAI\OpenAIProvider;
use LlmExe\State\Message;
use LlmExe\Streaming\StreamCompleted;
use LlmExe\Streaming\TextDelta;

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
║        Human-in-the-Loop Agent Demo                   ║
╚═══════════════════════════════════════════════════════╝" . RESET . "

This agent will ask for your approval before executing risky actions.

" . BOLD . "Risk Levels:" . RESET . "
  " . DIM . "low" . RESET . "      → No approval needed (read_file)
  " . YELLOW . "medium" . RESET . "   → Approval requested (write_file, send_email)
  " . RED . "high" . RESET . "     → Approval required (delete_files)
  " . RED . BOLD . "critical" . RESET . " → Approval required (execute_command)

" . BOLD . "Commands:" . RESET . " /exit to quit
" . CYAN . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . RESET . "
";

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

$approvalProvider = new CliApprovalProvider();
$tools = new ApprovingUseExecutors($baseTools, $approvalProvider, minimumRiskForApproval: 'medium');

// System prompt
$systemPrompt = "You are a helpful assistant with access to file system and command tools.
You can read, write, and delete files, execute shell commands, and send emails.
Always explain what you're about to do before using a tool.
Available tools: read_file, write_file, delete_files, execute_command, send_email";

$prompt = (new TextPrompt())->setContent('{{message}}');
$messages = [Message::system($systemPrompt)];

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
        maxIterations: 5,
        maxTokens: 1024,
    );

    echo "\n" . BOLD . CYAN . "Agent: " . RESET;

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

**Step 2: Test it runs**

Run: `php -l examples/approval-agent-cli.php`

---

## Task 6: Add getAttributes to CallableExecutor

**Files:**
- Modify: `src/Executor/CallableExecutor.php`

**Step 1: Check if attributes already exist**

Read the CallableExecutor to see current implementation.

**Step 2: Add attributes support if needed**

Add a `$attributes` constructor parameter and `getAttributes()` method if not present:

```php
public function __construct(
    // ... existing params ...
    private readonly array $attributes = [],
) {}

/** @return array<string, mixed> */
public function getAttributes(): array
{
    return $this->attributes;
}
```

**Step 3: Verify**

Run: `./vendor/bin/phpstan analyse src/Executor/CallableExecutor.php`

---

## Task 7: Write Tests

**Files:**
- Create: `tests/Unit/Examples/ApprovalAgentTest.php`

**Step 1: Create test file**

```php
<?php
declare(strict_types=1);

namespace LlmExe\Tests\Unit\Examples;

require_once __DIR__ . '/../../../examples/approval-agent/ApprovalRequest.php';
require_once __DIR__ . '/../../../examples/approval-agent/ApprovalDecision.php';
require_once __DIR__ . '/../../../examples/approval-agent/ApprovalProviderInterface.php';
require_once __DIR__ . '/../../../examples/approval-agent/ApprovingUseExecutors.php';
require_once __DIR__ . '/../../../examples/approval-agent/RiskyTools.php';

use LlmExe\Examples\ApprovalAgent\ApprovalAction;
use LlmExe\Examples\ApprovalAgent\ApprovalDecision;
use LlmExe\Examples\ApprovalAgent\ApprovalProviderInterface;
use LlmExe\Examples\ApprovalAgent\ApprovalRequest;
use LlmExe\Examples\ApprovalAgent\ApprovingUseExecutors;
use LlmExe\Examples\ApprovalAgent\RiskyTools;
use PHPUnit\Framework\TestCase;

final class ApprovalAgentTest extends TestCase
{
    public function test_approval_request_formats_correctly(): void
    {
        $request = new ApprovalRequest(
            toolName: 'delete_files',
            arguments: ['pattern' => '*.tmp'],
            riskLevel: 'high',
            description: 'Permanently deletes files',
        );

        $formatted = $request->format();
        $this->assertStringContainsString('delete_files', $formatted);
        $this->assertStringContainsString('high', $formatted);
    }

    public function test_approval_decision_factory_methods(): void
    {
        $approve = ApprovalDecision::approve();
        $this->assertSame(ApprovalAction::Approve, $approve->action);

        $reject = ApprovalDecision::reject('Too risky');
        $this->assertSame(ApprovalAction::Reject, $reject->action);
        $this->assertSame('Too risky', $reject->reason);

        $edit = ApprovalDecision::edit(['pattern' => 'safe.txt']);
        $this->assertSame(ApprovalAction::Edit, $edit->action);
        $this->assertSame(['pattern' => 'safe.txt'], $edit->editedArguments);
    }

    public function test_low_risk_tools_execute_without_approval(): void
    {
        $mockProvider = new class implements ApprovalProviderInterface {
            public int $callCount = 0;
            public function requestApproval(ApprovalRequest $request): ApprovalDecision {
                $this->callCount++;
                return ApprovalDecision::approve();
            }
        };

        $tools = new ApprovingUseExecutors(
            [RiskyTools::readFile()],
            $mockProvider,
            minimumRiskForApproval: 'medium',
        );

        $result = $tools->callFunction('read_file', ['path' => 'test.txt']);
        
        $this->assertSame(0, $mockProvider->callCount); // No approval requested
        $this->assertIsString($result);
    }

    public function test_high_risk_tools_require_approval(): void
    {
        $mockProvider = new class implements ApprovalProviderInterface {
            public int $callCount = 0;
            public function requestApproval(ApprovalRequest $request): ApprovalDecision {
                $this->callCount++;
                return ApprovalDecision::approve();
            }
        };

        $tools = new ApprovingUseExecutors(
            [RiskyTools::deleteFiles()],
            $mockProvider,
            minimumRiskForApproval: 'medium',
        );

        $result = $tools->callFunction('delete_files', ['pattern' => '*.tmp']);
        
        $this->assertSame(1, $mockProvider->callCount); // Approval was requested
    }

    public function test_rejected_tool_returns_error(): void
    {
        $mockProvider = new class implements ApprovalProviderInterface {
            public function requestApproval(ApprovalRequest $request): ApprovalDecision {
                return ApprovalDecision::reject('Not allowed');
            }
        };

        $tools = new ApprovingUseExecutors(
            [RiskyTools::deleteFiles()],
            $mockProvider,
        );

        $result = $tools->callFunction('delete_files', ['pattern' => '*.tmp']);
        $decoded = json_decode($result, true);
        
        $this->assertSame('Action rejected by user', $decoded['error']);
        $this->assertSame('Not allowed', $decoded['reason']);
    }

    public function test_edited_tool_uses_new_arguments(): void
    {
        $capturedArgs = null;
        
        $mockProvider = new class implements ApprovalProviderInterface {
            public function requestApproval(ApprovalRequest $request): ApprovalDecision {
                return ApprovalDecision::edit(['pattern' => 'safe-only.txt']);
            }
        };

        $tools = new ApprovingUseExecutors(
            [RiskyTools::deleteFiles()],
            $mockProvider,
        );

        $result = $tools->callFunction('delete_files', ['pattern' => '*.tmp']);
        $decoded = json_decode($result, true);
        
        // The edited pattern should be used
        $this->assertSame('safe-only.txt', $decoded['pattern']);
    }
}
```

**Step 2: Run tests**

Run: `./vendor/bin/phpunit tests/Unit/Examples/ApprovalAgentTest.php --no-coverage`

---

## Task 8: Final Verification

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
