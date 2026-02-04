<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Hooks\Events;

use HelgeSverre\Synapse\Streaming\StreamEvent;

/**
 * Dispatched for each stream event (TextDelta, ToolCallDelta, etc.).
 */
final readonly class OnStreamChunk
{
    public function __construct(
        public StreamEvent $event,
    ) {}
}
