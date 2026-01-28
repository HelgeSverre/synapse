<?php

declare(strict_types=1);

namespace LlmExe\Hooks\Events;

use LlmExe\Executor\StreamingResult;

/**
 * Dispatched when streaming execution completes successfully.
 */
final readonly class OnStreamSuccess
{
    public function __construct(
        public StreamingResult $result,
        public float $durationMs,
    ) {}
}
