<?php

declare(strict_types=1);

namespace LlmExe\Hooks\Events;

use LlmExe\Streaming\StreamEvent;

/**
 * Dispatched for each stream event (TextDelta, ToolCallDelta, etc.).
 */
final readonly class OnStreamChunk
{
    public function __construct(
        public StreamEvent $event,
    ) {}
}
