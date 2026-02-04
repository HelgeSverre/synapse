<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Hooks\Events;

use HelgeSverre\Synapse\Executor\ExecutionResult;

final readonly class OnSuccess
{
    public function __construct(
        public ExecutionResult $result,
        public float $durationMs,
    ) {}
}
