<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Evaluation;

use HelgeSverre\Synapse\Evaluation\EvalCase;
use HelgeSverre\Synapse\Evaluation\EvaluationSuite;
use HelgeSverre\Synapse\Evaluation\FilesystemSnapshotStore;
use HelgeSverre\Synapse\Evaluation\InMemorySnapshotStore;
use PHPUnit\Framework\TestCase;

final class EvaluationSuiteTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/synapse-evals-'.uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $files = glob($this->tmpDir.'/*/*') ?: [];
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            $dirs = glob($this->tmpDir.'/*') ?: [];
            foreach ($dirs as $dir) {
                if (is_dir($dir)) {
                    rmdir($dir);
                }
            }

            rmdir($this->tmpDir);
        }
    }

    public function test_eval_suite_with_expected_values(): void
    {
        $suite = new EvaluationSuite(
            name: 'math',
            subject: static fn (array $input): int => $input['a'] + $input['b'],
            cases: [
                EvalCase::expect('sum-1', ['a' => 1, 'b' => 2], 3),
                EvalCase::expect('sum-2', ['a' => 2, 'b' => 2], 5),
            ],
        );

        $report = $suite->run();

        $this->assertSame(2, $report->total);
        $this->assertSame(1, $report->passed);
        $this->assertSame(1, $report->failed);
        $this->assertFalse($report->isSuccessful());
    }

    public function test_eval_suite_snapshot_record_and_assert_with_in_memory_store(): void
    {
        $store = new InMemorySnapshotStore;

        $recordSuite = new EvaluationSuite(
            name: 'snapshot-suite',
            subject: static fn (array $input): array => ['answer' => strtoupper((string) $input['q'])],
            cases: [EvalCase::snapshot('case-1', ['q' => 'php'])],
            snapshotStore: $store,
            recordSnapshots: true,
        );

        $recordReport = $recordSuite->run();
        $this->assertTrue($recordReport->isSuccessful());

        $assertSuite = new EvaluationSuite(
            name: 'snapshot-suite',
            subject: static fn (array $input): array => ['answer' => strtoupper((string) $input['q'])],
            cases: [EvalCase::snapshot('case-1', ['q' => 'php'])],
            snapshotStore: $store,
            recordSnapshots: false,
        );

        $assertReport = $assertSuite->run();
        $this->assertTrue($assertReport->isSuccessful());
    }

    public function test_filesystem_snapshot_store_roundtrip(): void
    {
        $store = new FilesystemSnapshotStore($this->tmpDir);

        $store->save('suite', 'case', ['ok' => true]);

        $this->assertTrue($store->has('suite', 'case'));
        $this->assertSame(['ok' => true], $store->load('suite', 'case'));
    }

    public function test_filesystem_snapshot_store_avoids_path_collisions_for_distinct_keys(): void
    {
        $store = new FilesystemSnapshotStore($this->tmpDir);

        $store->save('suite/a', 'case', ['value' => 1]);
        $store->save('suite_a', 'case', ['value' => 2]);

        $this->assertSame(['value' => 1], $store->load('suite/a', 'case'));
        $this->assertSame(['value' => 2], $store->load('suite_a', 'case'));
    }

    public function test_from_executor_uses_run_method(): void
    {
        $executor = new class
        {
            public function run(array $input): string
            {
                return "hello {$input['name']}";
            }
        };

        $suite = EvaluationSuite::fromExecutor(
            name: 'executor-suite',
            executor: $executor,
            cases: [EvalCase::expect('hello', ['name' => 'world'], 'hello world')],
        );

        $report = $suite->run();

        $this->assertTrue($report->isSuccessful());
    }
}
