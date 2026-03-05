<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Evaluation;

final readonly class EvaluationReport
{
    /**
     * @param  list<EvalCaseResult>  $cases
     */
    public function __construct(
        public string $suite,
        public int $total,
        public int $passed,
        public int $failed,
        public array $cases,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->failed === 0;
    }
}
