<?php

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';

use function HelgeSverre\Synapse\createEvaluationSuite;
use function HelgeSverre\Synapse\createFilesystemSnapshotStore;

use HelgeSverre\Synapse\Evaluation\EvalCase;

$snapshotDir = sys_get_temp_dir().'/synapse-eval-snapshots';
$store = createFilesystemSnapshotStore($snapshotDir);

$subject = static function (array $input): array {
    return [
        'topic' => $input['topic'],
        'length' => strlen((string) $input['topic']),
    ];
};

$cases = [
    EvalCase::expect('length-is-correct', ['topic' => 'php'], ['topic' => 'php', 'length' => 3]),
    EvalCase::snapshot('snapshot-shape', ['topic' => 'synapse']),
];

$recordMode = ! $store->has('topic-metrics', 'snapshot-shape');

$suite = createEvaluationSuite(
    name: 'topic-metrics',
    subject: $subject,
    cases: $cases,
    snapshotStore: $store,
    recordSnapshots: $recordMode,
);

$report = $suite->run();

echo "Suite: {$report->suite}\n";
echo "Passed: {$report->passed}/{$report->total}\n";

echo "\nCase details:\n";
foreach ($report->cases as $case) {
    echo "- {$case->name}: ".($case->passed ? 'pass' : 'fail');
    if (! $case->passed && $case->message !== null) {
        echo " ({$case->message})";
    }
    echo "\n";
}
