<?php

declare(strict_types=1);

namespace LlmExe\Hooks\Events;

use LlmExe\Executor\ExecutionResult;

final readonly class OnSuccess
{
    public function __construct(
        public ExecutionResult $result,
        public float $durationMs,
    ) {}
}
