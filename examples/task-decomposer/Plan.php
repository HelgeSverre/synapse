<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\TaskDecomposer;

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
            fn (PlanStep $s) => $s->id,
            array_filter($this->steps, fn (PlanStep $s) => $s->status === StepStatus::Done),
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
        $lines = ["Goal: {$this->goal}", 'Steps:'];

        foreach ($this->steps as $step) {
            $status = match ($step->status) {
                StepStatus::Done => '[done]',
                StepStatus::Failed => '[fail]',
                StepStatus::Running => '[run] ',
                StepStatus::Skipped => '[skip]',
                StepStatus::Pending => '[pend]',
            };
            $lines[] = "  {$status} [{$step->id}] {$step->title}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{goal: string, steps: list<array{id: string, title: string, prompt: string, depends_on?: list<string>, tools?: list<string>}>}  $data
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
