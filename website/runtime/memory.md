# Checkpoints and Memory

Use checkpoints to resume long-running flows and memory stores for durable conversational state.

## RunCheckpointStore

```php
use HelgeSverre\Synapse\Runtime\Checkpoint\RunCheckpoint;
use function HelgeSverre\Synapse\createRunCheckpointStore;

$checkpoints = createRunCheckpointStore();

$checkpoints->save(new RunCheckpoint(
    runId: 'run_123',
    key: 'step.generate',
    payload: ['status' => 'ok'],
));

$latest = $checkpoints->get('run_123', 'step.generate');
```

## MemoryStore

```php
use function HelgeSverre\Synapse\createMemoryStore;

$memory = createMemoryStore();
$memory->put('user-42', 'preferences', ['tone' => 'brief'], ['profile']);

$entry = $memory->get('user-42', 'preferences');
$profileEntries = $memory->searchByTag('user-42', 'profile');
```

See `examples/production/checkpoints-and-memory.php`.
