<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Workflow;

use HelgeSverre\Synapse\Executor\ExecutionResult;
use HelgeSverre\Synapse\State\ConversationState;

final class WorkflowEngine
{
    /** @var list<WorkflowStep> */
    private array $steps;

    private ConversationState $state;

    /**
     * @param  list<WorkflowStep>  $steps
     */
    public function __construct(array $steps, ?ConversationState $state = null)
    {
        $this->steps = $steps;
        $this->state = $state ?? new ConversationState;

        $known = [];
        foreach ($this->steps as $step) {
            if (isset($known[$step->name])) {
                throw new \InvalidArgumentException("Duplicate workflow step: {$step->name}");
            }
            $known[$step->name] = true;
        }

        foreach ($this->steps as $step) {
            foreach ($step->dependsOn as $dependency) {
                if (! isset($known[$dependency])) {
                    throw new \InvalidArgumentException("Unknown dependency '{$dependency}' for step '{$step->name}'");
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function run(array $input = []): WorkflowResult
    {
        $results = [];
        $data = $input;

        /** @var array<string, WorkflowStep> $pending */
        $pending = [];
        foreach ($this->steps as $step) {
            $pending[$step->name] = $step;
        }

        while ($pending !== []) {
            $progress = false;

            foreach ($this->steps as $orderedStep) {
                $step = $pending[$orderedStep->name] ?? null;
                if ($step === null) {
                    continue;
                }

                if (! $this->dependenciesResolved($step, $results)) {
                    continue;
                }

                $progress = true;
                unset($pending[$step->name]);

                $blockedDependency = $this->firstFailedDependency($step, $results);
                if ($blockedDependency !== null) {
                    $results[$step->name] = new WorkflowStepResult(
                        step: $step->name,
                        success: false,
                        skipped: true,
                        attempts: 0,
                        durationMs: 0.0,
                        output: null,
                        error: "dependency_failed: {$blockedDependency}",
                        context: $data,
                    );

                    continue;
                }

                if ($step->when !== null && ! ($step->when)($data, $results)) {
                    $results[$step->name] = new WorkflowStepResult(
                        step: $step->name,
                        success: true,
                        skipped: true,
                        attempts: 0,
                        durationMs: 0.0,
                        output: null,
                        error: null,
                        context: $data,
                    );

                    continue;
                }

                $result = $this->executeStep($step, $data);
                $results[$step->name] = $result;

                if ($result->success) {
                    $data[$step->name] = $result->output;

                    continue;
                }

                if (! $step->continueOnError) {
                    return new WorkflowResult(
                        success: false,
                        steps: $results,
                        data: $data,
                        state: $this->state,
                    );
                }
            }

            if (! $progress) {
                $remaining = implode(', ', array_keys($pending));
                throw new \RuntimeException("Workflow is stuck. Circular dependency or unresolved gating among: {$remaining}");
            }
        }

        $allSucceeded = ! array_filter(
            $results,
            static fn (WorkflowStepResult $result): bool => ! $result->success,
        );

        return new WorkflowResult(
            success: $allSucceeded,
            steps: $results,
            data: $data,
            state: $this->state,
        );
    }

    /**
     * @param  array<string, WorkflowStepResult>  $results
     */
    private function dependenciesResolved(WorkflowStep $step, array $results): bool
    {
        foreach ($step->dependsOn as $dependency) {
            if (! isset($results[$dependency])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, WorkflowStepResult>  $results
     */
    private function firstFailedDependency(WorkflowStep $step, array $results): ?string
    {
        foreach ($step->dependsOn as $dependency) {
            $result = $results[$dependency] ?? null;
            if ($result === null) {
                continue;
            }

            if (! $result->success) {
                return $dependency;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function executeStep(WorkflowStep $step, array $context): WorkflowStepResult
    {
        $started = hrtime(true);
        $attempts = 0;
        $lastError = null;

        while ($attempts < $step->retryPolicy->maxAttempts) {
            $attempts++;

            try {
                $output = ($step->handler)($context, $this->state);

                if ($output instanceof ExecutionResult) {
                    $this->state = $output->state;
                }

                return new WorkflowStepResult(
                    step: $step->name,
                    success: true,
                    skipped: false,
                    attempts: $attempts,
                    durationMs: (hrtime(true) - $started) / 1_000_000,
                    output: $output,
                    error: null,
                    context: $context,
                );
            } catch (\Throwable $e) {
                $lastError = $e;

                if ($attempts < $step->retryPolicy->maxAttempts && $step->retryPolicy->delayMs > 0) {
                    usleep($step->retryPolicy->delayMs * 1000);
                }
            }
        }

        return new WorkflowStepResult(
            step: $step->name,
            success: false,
            skipped: false,
            attempts: $attempts,
            durationMs: (hrtime(true) - $started) / 1_000_000,
            output: null,
            error: $lastError?->getMessage() ?? 'Unknown workflow step failure',
            context: $context,
        );
    }
}
