<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\TaskDecomposer;

use HelgeSverre\Synapse\Executor\CallableExecutor;

final class SubmitPlanTool
{
    private ?Plan $lastValidPlan = null;

    public function create(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'submit_plan',
            description: 'Submit a structured execution plan. The plan will be validated for correctness. If invalid, errors will be returned and you should fix and resubmit.',
            handler: function (array $args): string {
                $validator = new PlanValidator;
                $result = $validator->validate($args);

                if (! $result['valid']) {
                    return json_encode([
                        'ok' => false,
                        'errors' => $result['errors'],
                        'hint' => 'Fix the errors and call submit_plan again with corrected plan.',
                    ], JSON_THROW_ON_ERROR);
                }

                $this->lastValidPlan = $result['plan'];

                return json_encode([
                    'ok' => true,
                    'message' => 'Plan validated successfully',
                    'step_count' => count($result['plan']->getSteps()),
                ], JSON_THROW_ON_ERROR);
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'goal' => [
                        'type' => 'string',
                        'description' => 'The high-level goal this plan achieves',
                    ],
                    'steps' => [
                        'type' => 'array',
                        'description' => 'Ordered list of steps to execute',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => [
                                    'type' => 'string',
                                    'description' => 'Unique step identifier (e.g., "s1", "s2")',
                                ],
                                'title' => [
                                    'type' => 'string',
                                    'description' => 'Short title for the step',
                                ],
                                'prompt' => [
                                    'type' => 'string',
                                    'description' => 'Detailed instructions for executing this step',
                                ],
                                'depends_on' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                    'description' => 'IDs of steps that must complete before this one',
                                ],
                                'tools' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                    'description' => 'Names of tools needed for this step',
                                ],
                            ],
                            'required' => ['id', 'title', 'prompt'],
                        ],
                    ],
                ],
                'required' => ['goal', 'steps'],
            ],
        );
    }

    public function getLastValidPlan(): ?Plan
    {
        return $this->lastValidPlan;
    }

    public function reset(): void
    {
        $this->lastValidPlan = null;
    }
}
