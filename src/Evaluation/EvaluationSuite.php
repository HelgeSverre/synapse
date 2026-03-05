<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Evaluation;

use HelgeSverre\Synapse\Executor\ExecutionResult;

final class EvaluationSuite
{
    /** @var \Closure(array<string, mixed>): mixed */
    private \Closure $subject;

    /** @var list<EvalCase> */
    private array $cases;

    /**
     * @param  callable(array<string, mixed>): mixed  $subject
     * @param  list<EvalCase>  $cases
     */
    public function __construct(
        private readonly string $name,
        callable $subject,
        array $cases = [],
        private readonly ?SnapshotStoreInterface $snapshotStore = null,
        private readonly bool $recordSnapshots = false,
    ) {
        $this->subject = \Closure::fromCallable($subject);
        $this->cases = $cases;
    }

    /**
     * @param  list<EvalCase>  $cases
     */
    public static function fromExecutor(
        string $name,
        object $executor,
        array $cases = [],
        ?SnapshotStoreInterface $snapshotStore = null,
        bool $recordSnapshots = false,
    ): self {
        if (! method_exists($executor, 'run')) {
            throw new \InvalidArgumentException('Executor must expose a run(array $input) method.');
        }

        return new self(
            name: $name,
            subject: static fn (array $input): mixed => $executor->run($input),
            cases: $cases,
            snapshotStore: $snapshotStore,
            recordSnapshots: $recordSnapshots,
        );
    }

    public function addCase(EvalCase $case): self
    {
        $clone = clone $this;
        $clone->cases[] = $case;

        return $clone;
    }

    /**
     * @param  list<EvalCase>|null  $cases
     */
    public function run(?array $cases = null): EvaluationReport
    {
        $activeCases = $cases ?? $this->cases;
        $results = [];

        foreach ($activeCases as $case) {
            $results[] = $this->evaluateCase($case);
        }

        $passed = count(array_filter($results, static fn (EvalCaseResult $result): bool => $result->passed));
        $total = count($results);

        return new EvaluationReport(
            suite: $this->name,
            total: $total,
            passed: $passed,
            failed: $total - $passed,
            cases: $results,
        );
    }

    private function evaluateCase(EvalCase $case): EvalCaseResult
    {
        try {
            $raw = ($this->subject)($case->input);
            $actual = $raw instanceof ExecutionResult ? $raw->getValue() : $raw;

            $expected = null;
            if ($case->hasExpected) {
                $expected = $case->expected;
            } elseif ($case->useSnapshot) {
                $snapshotKey = $case->snapshotKey ?? $case->name;

                if ($this->snapshotStore === null) {
                    return new EvalCaseResult(
                        name: $case->name,
                        passed: false,
                        actual: $actual,
                        expected: null,
                        message: 'Snapshot mode requires a SnapshotStoreInterface implementation.',
                    );
                }

                if ($this->recordSnapshots) {
                    $this->snapshotStore->save($this->name, $snapshotKey, $actual);
                    $expected = $actual;
                } else {
                    if (! $this->snapshotStore->has($this->name, $snapshotKey)) {
                        return new EvalCaseResult(
                            name: $case->name,
                            passed: false,
                            actual: $actual,
                            expected: null,
                            message: "Missing snapshot: {$this->name}/{$snapshotKey}",
                        );
                    }

                    $expected = $this->snapshotStore->load($this->name, $snapshotKey);
                }
            }

            $passed = $case->matcher !== null
                ? ($case->matcher)($actual, $expected)
                : $actual === $expected;

            return new EvalCaseResult(
                name: $case->name,
                passed: $passed,
                actual: $actual,
                expected: $expected,
                message: $passed ? null : 'Actual output did not match expected output.',
            );
        } catch (\Throwable $e) {
            return new EvalCaseResult(
                name: $case->name,
                passed: false,
                actual: null,
                expected: $case->expected,
                message: $e->getMessage(),
            );
        }
    }
}
