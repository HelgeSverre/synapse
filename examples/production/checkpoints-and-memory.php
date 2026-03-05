<?php

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';

use function HelgeSverre\Synapse\createMemoryStore;
use function HelgeSverre\Synapse\createRunCheckpointStore;

use HelgeSverre\Synapse\Runtime\Checkpoint\RunCheckpoint;

$checkpoints = createRunCheckpointStore();
$memory = createMemoryStore();

$runId = 'run_'.date('Ymd_His');

$checkpoints->save(new RunCheckpoint(
    runId: $runId,
    key: 'step.fetch_context',
    payload: ['status' => 'completed', 'documents' => 5],
));

$checkpoints->save(new RunCheckpoint(
    runId: $runId,
    key: 'step.generate_answer',
    payload: ['status' => 'completed', 'tokens' => 284],
));

$memory->put('support-user-42', 'latest_question', 'How do I reset my API key?', ['session', 'recent']);
$memory->put('support-user-42', 'preferred_style', 'brief', ['profile']);

echo "Checkpoints for {$runId}:\n";
foreach ($checkpoints->list($runId) as $checkpoint) {
    echo "- {$checkpoint->key}: ".json_encode($checkpoint->payload)."\n";
}

echo "\nMemory tagged 'session':\n";
foreach ($memory->searchByTag('support-user-42', 'session') as $entry) {
    echo "- {$entry->key}: ".json_encode($entry->value)."\n";
}
