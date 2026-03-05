# WorkflowEngine

`WorkflowEngine` runs named steps with dependencies, retry policies, and optional conditional gating.

## Quick Example

```php
use HelgeSverre\Synapse\Workflow\WorkflowRetryPolicy;
use HelgeSverre\Synapse\Workflow\WorkflowStep;
use function HelgeSverre\Synapse\createWorkflowEngine;

$workflow = createWorkflowEngine([
    new WorkflowStep('fetch', fn () => ['items' => [1, 2, 3]]),
    new WorkflowStep(
        name: 'transform',
        dependsOn: ['fetch'],
        handler: fn (array $ctx) => array_sum($ctx['fetch']['items']),
    ),
    new WorkflowStep(
        name: 'publish',
        dependsOn: ['transform'],
        retryPolicy: new WorkflowRetryPolicy(maxAttempts: 3, delayMs: 50),
        handler: fn (array $ctx) => "published: {$ctx['transform']}",
    ),
]);

$result = $workflow->run();
```

## Step Features

- `dependsOn`: wait for prerequisite steps
- `when`: conditional execution callback
- `retryPolicy`: max attempts + delay
- `continueOnError`: continue pipeline after a failed step

See `examples/production/workflow-engine.php`.
