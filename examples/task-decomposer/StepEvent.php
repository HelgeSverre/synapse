<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\TaskDecomposer;

/**
 * Event emitted during plan execution.
 */
final readonly class StepEvent
{
    public function __construct(
        public string $type, // plan_started, step_started, step_completed, step_failed, step_abandoned, plan_completed, plan_failed
        public ?string $stepId,
        public string $message,
    ) {}
}
