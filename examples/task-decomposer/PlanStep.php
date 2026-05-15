<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\TaskDecomposer;

final class PlanStep
{
    public StepStatus $status = StepStatus::Pending;

    public ?string $output = null;

    public ?string $error = null;

    public int $attempts = 0;

    /**
     * @param  list<string>  $dependsOn
     * @param  list<string>  $tools
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

    /**
     * @param  list<string>  $completedStepIds
     */
    public function canRun(array $completedStepIds): bool
    {
        if ($this->status !== StepStatus::Pending) {
            return false;
        }

        foreach ($this->dependsOn as $dep) {
            if (! in_array($dep, $completedStepIds, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{id: string, title: string, status: string, output: ?string, error: ?string, attempts: int}
     */
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
