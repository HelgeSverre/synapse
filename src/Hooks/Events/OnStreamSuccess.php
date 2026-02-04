<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Hooks\Events;

use HelgeSverre\Synapse\Executor\StreamingResult;

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
