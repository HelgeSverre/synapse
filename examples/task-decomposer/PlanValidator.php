<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\TaskDecomposer;

final class PlanValidator
{
    private const MAX_STEPS = 20;

    /**
     * Validate a plan structure.
     *
     * @param  array<string, mixed>  $data
     * @return array{valid: bool, errors: list<string>, plan: ?Plan}
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Check required fields
        if (! isset($data['goal']) || ! is_string($data['goal'])) {
            $errors[] = "Missing or invalid 'goal' field";
        }

        if (! isset($data['steps']) || ! is_array($data['steps'])) {
            $errors[] = "Missing or invalid 'steps' field";

            return ['valid' => false, 'errors' => $errors, 'plan' => null];
        }

        if (count($data['steps']) === 0) {
            $errors[] = 'Plan must have at least one step';
        }

        if (count($data['steps']) > self::MAX_STEPS) {
            $errors[] = 'Plan exceeds maximum of '.self::MAX_STEPS.' steps';
        }

        // Validate steps
        $stepIds = [];
        foreach ($data['steps'] as $i => $step) {
            if (! is_array($step)) {
                $errors[] = "Step {$i}: must be an object";

                continue;
            }

            if (! isset($step['id']) || ! is_string($step['id'])) {
                $errors[] = "Step {$i}: missing or invalid 'id'";

                continue;
            }

            if (in_array($step['id'], $stepIds, true)) {
                $errors[] = "Step {$i}: duplicate id '{$step['id']}'";
            }
            $stepIds[] = $step['id'];

            if (! isset($step['title']) || ! is_string($step['title'])) {
                $errors[] = "Step {$step['id']}: missing or invalid 'title'";
            }

            if (! isset($step['prompt']) || ! is_string($step['prompt'])) {
                $errors[] = "Step {$step['id']}: missing or invalid 'prompt'";
            }

            // Validate depends_on references
            if (isset($step['depends_on']) && is_array($step['depends_on'])) {
                $allIds = array_column($data['steps'], 'id');
                foreach ($step['depends_on'] as $dep) {
                    if (! is_string($dep)) {
                        $errors[] = "Step {$step['id']}: depends_on entries must be strings";

                        continue;
                    }
                    if (! in_array($dep, $allIds, true)) {
                        $errors[] = "Step {$step['id']}: depends on unknown step '{$dep}'";
                    }
                }
            }
        }

        // Check for cycles
        if (empty($errors)) {
            $cycleError = $this->detectCycle($data['steps']);
            if ($cycleError !== null) {
                $errors[] = $cycleError;
            }
        }

        if (! empty($errors)) {
            return ['valid' => false, 'errors' => $errors, 'plan' => null];
        }

        /** @var array{goal: string, steps: list<array{id: string, title: string, prompt: string, depends_on?: list<string>, tools?: list<string>}>} $data */
        return [
            'valid' => true,
            'errors' => [],
            'plan' => Plan::fromArray($data),
        ];
    }

    /**
     * Detect cycles in the dependency graph using DFS.
     *
     * @param  list<array<string, mixed>>  $steps
     */
    private function detectCycle(array $steps): ?string
    {
        $graph = [];
        foreach ($steps as $step) {
            /** @var array{id: string, depends_on?: list<string>} $step */
            $graph[$step['id']] = $step['depends_on'] ?? [];
        }

        $visited = [];
        $recStack = [];

        foreach (array_keys($graph) as $node) {
            if ($this->hasCycleDFS((string) $node, $graph, $visited, $recStack)) {
                return "Cycle detected in dependencies involving step '{$node}'";
            }
        }

        return null;
    }

    /**
     * @param  array<string, list<string>>  $graph
     * @param  array<string, bool>  $visited
     * @param  array<string, bool>  $recStack
     */
    private function hasCycleDFS(string $node, array $graph, array &$visited, array &$recStack): bool
    {
        if (isset($recStack[$node])) {
            return true; // Back edge = cycle
        }

        if (isset($visited[$node])) {
            return false; // Already processed
        }

        $visited[$node] = true;
        $recStack[$node] = true;

        foreach ($graph[$node] ?? [] as $dep) {
            if ($this->hasCycleDFS($dep, $graph, $visited, $recStack)) {
                return true;
            }
        }

        unset($recStack[$node]);

        return false;
    }
}
