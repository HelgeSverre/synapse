<?php

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';

use function HelgeSverre\Synapse\createWorkflowEngine;

use HelgeSverre\Synapse\Workflow\WorkflowRetryPolicy;
use HelgeSverre\Synapse\Workflow\WorkflowStep;

$attempts = 0;

$workflow = createWorkflowEngine([
    new WorkflowStep(
        name: 'fetch_data',
        handler: static fn (): array => ['items' => ['php', 'synapse', 'workflow']],
    ),
    new WorkflowStep(
        name: 'summarize',
        dependsOn: ['fetch_data'],
        handler: static function (array $context): string {
            $items = $context['fetch_data']['items'];

            return 'Summary: '.implode(', ', $items);
        },
    ),
    new WorkflowStep(
        name: 'publish',
        dependsOn: ['summarize'],
        retryPolicy: new WorkflowRetryPolicy(maxAttempts: 2, delayMs: 10),
        handler: static function (array $context) use (&$attempts): string {
            $attempts++;
            if ($attempts === 1) {
                throw new RuntimeException('temporary publish error');
            }

            return 'Published: '.$context['summarize'];
        },
    ),
]);

$result = $workflow->run();

echo 'Workflow success: '.($result->success ? 'true' : 'false')."\n";
foreach ($result->steps as $name => $stepResult) {
    echo "- {$name}: ".($stepResult->success ? 'ok' : 'failed')." ({$stepResult->attempts} attempts)\n";
}

echo "\nOutput:\n";
echo $result->getData('publish')."\n";
