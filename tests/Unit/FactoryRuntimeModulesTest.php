<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit;

use HelgeSverre\Synapse\Evaluation\EvaluationSuite;
use HelgeSverre\Synapse\Factory;
use HelgeSverre\Synapse\Runtime\Checkpoint\RunCheckpointStore;
use HelgeSverre\Synapse\Runtime\Memory\MemoryStore;
use HelgeSverre\Synapse\Trace\HookTraceBridge;
use HelgeSverre\Synapse\Trace\InMemoryTraceExporter;
use HelgeSverre\Synapse\Trace\TraceContext;
use HelgeSverre\Synapse\Workflow\WorkflowEngine;
use HelgeSverre\Synapse\Workflow\WorkflowStep;
use PHPUnit\Framework\TestCase;

final class FactoryRuntimeModulesTest extends TestCase
{
    public function test_factory_creates_trace_context_and_bridge(): void
    {
        $context = Factory::createTraceContext(['service' => 'test']);
        $exporter = Factory::createInMemoryTraceExporter();
        $bridge = Factory::createTraceBridge($exporter, $context);

        $this->assertInstanceOf(TraceContext::class, $context);
        $this->assertInstanceOf(InMemoryTraceExporter::class, $exporter);
        $this->assertInstanceOf(HookTraceBridge::class, $bridge);
    }

    public function test_factory_creates_runtime_stores(): void
    {
        $checkpointStore = Factory::createRunCheckpointStore();
        $memoryStore = Factory::createMemoryStore();

        $this->assertInstanceOf(RunCheckpointStore::class, $checkpointStore);
        $this->assertInstanceOf(MemoryStore::class, $memoryStore);
    }

    public function test_factory_creates_workflow_engine_and_evaluation_suite(): void
    {
        $engine = Factory::createWorkflowEngine([
            new WorkflowStep('first', static fn (): string => 'ok'),
        ]);

        $suite = Factory::createEvaluationSuite(
            name: 'suite',
            subject: static fn (array $input): string => $input['value'] ?? '',
        );

        $this->assertInstanceOf(WorkflowEngine::class, $engine);
        $this->assertInstanceOf(EvaluationSuite::class, $suite);
    }

    public function test_factory_validates_workflow_step_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Workflow steps must be WorkflowStep instances.');

        /** @var array<int, mixed> $steps */
        $steps = ['not-a-step'];
        Factory::createWorkflowEngine($steps);
    }

    public function test_factory_validates_eval_case_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Evaluation cases must be EvalCase instances.');

        Factory::createEvaluationSuite(
            name: 'suite',
            subject: static fn (array $input): mixed => $input,
            cases: ['invalid'],
        );
    }
}
