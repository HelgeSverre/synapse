<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Evaluation;

final readonly class EvalCaseResult
{
    public function __construct(
        public string $name,
        public bool $passed,
        public mixed $actual,
        public mixed $expected,
        public ?string $message = null,
    ) {}
}
