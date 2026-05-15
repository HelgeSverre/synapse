<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\TaskDecomposer;

use Generator;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutor;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\Streaming\StreamableProviderInterface;
use HelgeSverre\Synapse\Streaming\StreamEvent;
use HelgeSverre\Synapse\Streaming\TextDelta;

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
     * @return Generator<int, StepEvent|StreamEvent>
     */
    public function execute(Plan $plan): Generator
    {
        yield new StepEvent('plan_started', null, $plan->getSummary());

        while (! $plan->isComplete()) {
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
     * @return Generator<int, StreamEvent, mixed, string>
     */
    private function executeStep(PlanStep $step, Plan $plan): Generator
    {
        // Build context from completed dependencies
        $context = $this->buildStepContext($step, $plan);

        $prompt = (new TextPrompt)->setContent($context);

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
            '',
            '## Your Task',
            $step->prompt,
            '',
        ];

        // Add outputs from dependencies
        $depOutputs = [];
        foreach ($step->dependsOn as $depId) {
            $depStep = $plan->getStep($depId);
            if ($depStep !== null && $depStep->output !== null) {
                $depOutputs[] = "### Output from [{$depId}] {$depStep->title}\n{$depStep->output}";
            }
        }

        if (! empty($depOutputs)) {
            $parts[] = '## Context from Previous Steps';
            $parts = array_merge($parts, $depOutputs);
            $parts[] = '';
        }

        $parts[] = '## Instructions';
        $parts[] = 'Complete this step and provide a clear output. Be concise but thorough.';

        return implode("\n", $parts);
    }
}
