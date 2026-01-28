<?php

declare(strict_types=1);

namespace LlmExe\Hooks\Events;

use LlmExe\Streaming\StreamCompleted;

/**
 * Dispatched when streaming completes.
 */
final readonly class OnStreamEnd
{
    public function __construct(
        public StreamCompleted $completed,
        public string $fullText,
    ) {}
}
