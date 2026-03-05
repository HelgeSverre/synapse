# EvaluationSuite

`EvaluationSuite` runs deterministic test cases against a callable or executor and supports snapshot-based regression checks.

## Quick Example

```php
use HelgeSverre\Synapse\Evaluation\EvalCase;
use function HelgeSverre\Synapse\createEvaluationSuite;
use function HelgeSverre\Synapse\createFilesystemSnapshotStore;

$store = createFilesystemSnapshotStore(__DIR__.'/.snapshots');

$suite = createEvaluationSuite(
    name: 'topic-metrics',
    subject: fn (array $input) => ['len' => strlen($input['topic'])],
    cases: [
        EvalCase::expect('len-php', ['topic' => 'php'], ['len' => 3]),
        EvalCase::snapshot('len-synapse', ['topic' => 'synapse']),
    ],
    snapshotStore: $store,
    recordSnapshots: false,
);

$report = $suite->run();
```

## Snapshot Modes

- `recordSnapshots: true`: write new snapshots.
- `recordSnapshots: false`: assert against existing snapshots.

See `examples/production/evaluation-suite.php`.
