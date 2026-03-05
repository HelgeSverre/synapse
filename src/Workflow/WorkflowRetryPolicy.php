<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Workflow;

final readonly class WorkflowRetryPolicy
{
    public function __construct(
        public int $maxAttempts = 1,
        public int $delayMs = 0,
    ) {
        if ($this->maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts must be at least 1');
        }

        if ($this->delayMs < 0) {
            throw new \InvalidArgumentException('delayMs cannot be negative');
        }
    }
}
