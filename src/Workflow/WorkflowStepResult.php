<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Workflow;

final readonly class WorkflowStepResult
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $step,
        public bool $success,
        public bool $skipped,
        public int $attempts,
        public float $durationMs,
        public mixed $output = null,
        public ?string $error = null,
        public array $context = [],
    ) {}
}
