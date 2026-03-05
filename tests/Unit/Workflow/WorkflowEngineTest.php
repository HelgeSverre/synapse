<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Workflow;

use HelgeSverre\Synapse\Workflow\WorkflowEngine;
use HelgeSverre\Synapse\Workflow\WorkflowRetryPolicy;
use HelgeSverre\Synapse\Workflow\WorkflowStep;
use PHPUnit\Framework\TestCase;

final class WorkflowEngineTest extends TestCase
{
    public function test_runs_steps_with_dependencies_in_order(): void
    {
        $trace = [];

        $steps = [
            new WorkflowStep(
                name: 'fetch',
                handler: function () use (&$trace): array {
                    $trace[] = 'fetch';

                    return ['value' => 2];
                },
            ),
            new WorkflowStep(
                name: 'transform',
                dependsOn: ['fetch'],
                handler: function (array $context) use (&$trace): int {
                    $trace[] = 'transform';

                    return $context['fetch']['value'] * 3;
                },
            ),
        ];

        $result = (new WorkflowEngine($steps))->run(['input' => 'ok']);

        $this->assertTrue($result->success);
        $this->assertSame(['fetch', 'transform'], $trace);
        $this->assertSame(6, $result->getData('transform'));
    }

    public function test_retries_failed_step_according_to_policy(): void
    {
        $attempts = 0;

        $steps = [
            new WorkflowStep(
                name: 'unstable',
                retryPolicy: new WorkflowRetryPolicy(maxAttempts: 2),
                handler: function () use (&$attempts): string {
                    $attempts++;
                    if ($attempts === 1) {
                        throw new \RuntimeException('transient');
                    }

                    return 'ok';
                },
            ),
        ];

        $result = (new WorkflowEngine($steps))->run();

        $this->assertTrue($result->success);
        $this->assertSame(2, $result->getStep('unstable')?->attempts);
        $this->assertSame('ok', $result->getData('unstable'));
    }

    public function test_marks_dependent_step_as_blocked_after_failure_with_continue_on_error(): void
    {
        $steps = [
            new WorkflowStep(
                name: 'first',
                continueOnError: true,
                handler: static function (): never {
                    throw new \RuntimeException('failed');
                },
            ),
            new WorkflowStep(
                name: 'second',
                dependsOn: ['first'],
                handler: static fn (): string => 'should-not-run',
            ),
        ];

        $result = (new WorkflowEngine($steps))->run();

        $this->assertFalse($result->success);
        $this->assertFalse($result->getStep('first')?->success);
        $this->assertTrue($result->getStep('second')?->skipped);
        $this->assertSame('dependency_failed: first', $result->getStep('second')?->error);
    }

    public function test_detects_circular_dependency(): void
    {
        $steps = [
            new WorkflowStep('a', static fn (): string => 'a', dependsOn: ['b']),
            new WorkflowStep('b', static fn (): string => 'b', dependsOn: ['a']),
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Workflow is stuck');

        (new WorkflowEngine($steps))->run();
    }
}
