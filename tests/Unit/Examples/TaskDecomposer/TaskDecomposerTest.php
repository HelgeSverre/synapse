<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Examples\TaskDecomposer;

require_once __DIR__.'/../../../../examples/task-decomposer/StepStatus.php';
require_once __DIR__.'/../../../../examples/task-decomposer/PlanStep.php';
require_once __DIR__.'/../../../../examples/task-decomposer/Plan.php';
require_once __DIR__.'/../../../../examples/task-decomposer/PlanValidator.php';
require_once __DIR__.'/../../../../examples/task-decomposer/SubmitPlanTool.php';

use HelgeSverre\Synapse\Examples\TaskDecomposer\Plan;
use HelgeSverre\Synapse\Examples\TaskDecomposer\PlanStep;
use HelgeSverre\Synapse\Examples\TaskDecomposer\PlanValidator;
use HelgeSverre\Synapse\Examples\TaskDecomposer\StepStatus;
use HelgeSverre\Synapse\Examples\TaskDecomposer\SubmitPlanTool;
use PHPUnit\Framework\TestCase;

final class TaskDecomposerTest extends TestCase
{
    public function test_plan_step_tracks_status(): void
    {
        $step = new PlanStep('s1', 'Test', 'Do something');

        $this->assertSame(StepStatus::Pending, $step->status);

        $step->markRunning();
        $this->assertSame(StepStatus::Running, $step->status);
        $this->assertSame(1, $step->attempts);

        $step->markDone('Result');
        $this->assertSame(StepStatus::Done, $step->status);
        $this->assertSame('Result', $step->output);
    }

    public function test_plan_step_checks_dependencies(): void
    {
        $step = new PlanStep('s2', 'Test', 'Do something', dependsOn: ['s1']);

        $this->assertFalse($step->canRun([]));
        $this->assertTrue($step->canRun(['s1']));
    }

    public function test_plan_tracks_completion(): void
    {
        $plan = new Plan('Test goal');
        $plan->addStep(new PlanStep('s1', 'Step 1', 'First'));
        $plan->addStep(new PlanStep('s2', 'Step 2', 'Second', dependsOn: ['s1']));

        $this->assertFalse($plan->isComplete());

        $plan->getStep('s1')->markDone('Done 1');
        $this->assertFalse($plan->isComplete());

        $plan->getStep('s2')->markDone('Done 2');
        $this->assertTrue($plan->isComplete());
    }

    public function test_plan_returns_next_runnable_step(): void
    {
        $plan = new Plan('Test');
        $plan->addStep(new PlanStep('s1', 'First', 'Do first'));
        $plan->addStep(new PlanStep('s2', 'Second', 'Do second', dependsOn: ['s1']));

        $next = $plan->getNextRunnableStep();
        $this->assertNotNull($next);
        $this->assertSame('s1', $next->id);

        $plan->getStep('s1')->markDone('Done');

        $next = $plan->getNextRunnableStep();
        $this->assertNotNull($next);
        $this->assertSame('s2', $next->id);
    }

    public function test_validator_accepts_valid_plan(): void
    {
        $validator = new PlanValidator;

        $result = $validator->validate([
            'goal' => 'Test goal',
            'steps' => [
                ['id' => 's1', 'title' => 'First', 'prompt' => 'Do first'],
                ['id' => 's2', 'title' => 'Second', 'prompt' => 'Do second', 'depends_on' => ['s1']],
            ],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertInstanceOf(Plan::class, $result['plan']);
    }

    public function test_validator_rejects_missing_goal(): void
    {
        $validator = new PlanValidator;

        $result = $validator->validate([
            'steps' => [['id' => 's1', 'title' => 'Test', 'prompt' => 'Do']],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains("Missing or invalid 'goal' field", $result['errors']);
    }

    public function test_validator_rejects_duplicate_ids(): void
    {
        $validator = new PlanValidator;

        $result = $validator->validate([
            'goal' => 'Test',
            'steps' => [
                ['id' => 's1', 'title' => 'First', 'prompt' => 'Do'],
                ['id' => 's1', 'title' => 'Duplicate', 'prompt' => 'Do'],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            count(array_filter($result['errors'], fn ($e) => str_contains($e, 'duplicate'))) > 0,
        );
    }

    public function test_validator_rejects_cycles(): void
    {
        $validator = new PlanValidator;

        $result = $validator->validate([
            'goal' => 'Test',
            'steps' => [
                ['id' => 's1', 'title' => 'First', 'prompt' => 'Do', 'depends_on' => ['s2']],
                ['id' => 's2', 'title' => 'Second', 'prompt' => 'Do', 'depends_on' => ['s1']],
            ],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertTrue(
            count(array_filter($result['errors'], fn ($e) => str_contains($e, 'Cycle'))) > 0,
        );
    }

    public function test_submit_plan_tool_validates_and_stores(): void
    {
        $submitPlanTool = new SubmitPlanTool;
        $tool = $submitPlanTool->create();

        $result = $tool->execute([
            'goal' => 'Test goal',
            'steps' => [
                ['id' => 's1', 'title' => 'First', 'prompt' => 'Do first'],
            ],
        ]);

        $this->assertIsString($result->result);
        $decoded = json_decode($result->result, true);
        $this->assertIsArray($decoded);
        $this->assertTrue($decoded['ok']);
        $this->assertNotNull($submitPlanTool->getLastValidPlan());
    }

    public function test_submit_plan_tool_returns_errors_for_invalid(): void
    {
        $submitPlanTool = new SubmitPlanTool;
        $tool = $submitPlanTool->create();

        $result = $tool->execute([
            'steps' => [], // Missing goal, empty steps
        ]);

        $this->assertIsString($result->result);
        $decoded = json_decode($result->result, true);
        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['ok']);
        $this->assertNotEmpty($decoded['errors']);
    }
}
