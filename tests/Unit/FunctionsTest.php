<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit;

use function HelgeSverre\Synapse\createEvaluationSuite;
use function HelgeSverre\Synapse\createExecutor;
use function HelgeSverre\Synapse\createInMemoryTraceExporter;
use function HelgeSverre\Synapse\createMemoryStore;
use function HelgeSverre\Synapse\createPrompt;
use function HelgeSverre\Synapse\createRunCheckpointStore;
use function HelgeSverre\Synapse\createToolRegistry;
use function HelgeSverre\Synapse\createTraceBridge;
use function HelgeSverre\Synapse\createTraceContext;
use function HelgeSverre\Synapse\createWorkflowEngine;

use HelgeSverre\Synapse\Evaluation\EvaluationSuite;
use HelgeSverre\Synapse\Executor\LlmExecutor;
use HelgeSverre\Synapse\Executor\ToolRegistry;
use HelgeSverre\Synapse\Options\ExecutorOptions;
use HelgeSverre\Synapse\Prompt\ChatPrompt;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\Runtime\Checkpoint\RunCheckpointStore;
use HelgeSverre\Synapse\Runtime\Memory\MemoryStore;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Trace\HookTraceBridge;
use HelgeSverre\Synapse\Trace\TraceContext;
use HelgeSverre\Synapse\Workflow\WorkflowEngine;
use HelgeSverre\Synapse\Workflow\WorkflowStep;
use PHPUnit\Framework\TestCase;

final class FunctionsTest extends TestCase
{
    public function test_create_executor_returns_llm_executor_for_non_stream_options(): void
    {
        $provider = new class implements LlmProviderInterface
        {
            public function generate(GenerationRequest $request): GenerationResponse
            {
                return new GenerationResponse(
                    text: 'ok',
                    messages: [Message::assistant('ok')],
                    toolCalls: [],
                    model: $request->model,
                );
            }

            public function getCapabilities(): ProviderCapabilities
            {
                return new ProviderCapabilities;
            }

            public function getName(): string
            {
                return 'fake';
            }
        };

        $executor = createExecutor(new ExecutorOptions(
            llm: $provider,
            prompt: \HelgeSverre\Synapse\Factory::createTextPrompt()->setContent('hello'),
            model: 'fake-model',
        ));

        $this->assertInstanceOf(LlmExecutor::class, $executor);
    }

    public function test_create_prompt_creates_chat_prompt_by_default(): void
    {
        $prompt = createPrompt();

        $this->assertInstanceOf(ChatPrompt::class, $prompt);
    }

    public function test_create_prompt_creates_text_prompt_when_requested(): void
    {
        $prompt = createPrompt('text');

        $this->assertInstanceOf(TextPrompt::class, $prompt);
    }

    public function test_create_prompt_throws_for_invalid_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown prompt type: invalid. Expected 'chat' or 'text'.");

        createPrompt('invalid');
    }

    public function test_create_tool_registry_returns_tool_registry(): void
    {
        $registry = createToolRegistry([
            [
                'name' => 'echo_tool',
                'description' => 'Echo input',
                'handler' => fn (array $input): array => $input,
            ],
        ]);

        $this->assertInstanceOf(ToolRegistry::class, $registry);
        $this->assertTrue($registry->hasFunction('echo_tool'));
    }

    public function test_create_tool_registry_returns_registry_for_simple_config(): void
    {
        $registry = createToolRegistry([
            [
                'name' => 'noop',
                'description' => 'No-op',
                'handler' => fn (): string => 'ok',
            ],
        ]);

        $this->assertInstanceOf(ToolRegistry::class, $registry);
        $this->assertTrue($registry->hasFunction('noop'));
    }

    public function test_runtime_and_evaluation_function_helpers_create_expected_types(): void
    {
        $context = createTraceContext(['service' => 'test']);
        $bridge = createTraceBridge(createInMemoryTraceExporter(), $context);
        $checkpointStore = createRunCheckpointStore();
        $memoryStore = createMemoryStore();
        $workflow = createWorkflowEngine([
            new WorkflowStep('one', static fn (): string => 'ok'),
        ]);
        $suite = createEvaluationSuite('suite', static fn (array $input): mixed => $input['value'] ?? null);

        $this->assertInstanceOf(TraceContext::class, $context);
        $this->assertInstanceOf(HookTraceBridge::class, $bridge);
        $this->assertInstanceOf(RunCheckpointStore::class, $checkpointStore);
        $this->assertInstanceOf(MemoryStore::class, $memoryStore);
        $this->assertInstanceOf(WorkflowEngine::class, $workflow);
        $this->assertInstanceOf(EvaluationSuite::class, $suite);
    }

    public function test_runtime_helpers_validate_input_types(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Workflow steps must be WorkflowStep instances.');

        /** @var array<int, mixed> $steps */
        $steps = ['invalid'];
        createWorkflowEngine($steps);
    }

    public function test_create_evaluation_suite_validates_case_types(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Evaluation cases must be EvalCase instances.');

        createEvaluationSuite(
            name: 'suite',
            subject: static fn (array $input): mixed => $input,
            cases: ['invalid'],
        );
    }
}
