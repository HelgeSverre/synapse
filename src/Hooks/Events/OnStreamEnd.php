<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Hooks\Events;

use HelgeSverre\Synapse\Streaming\StreamCompleted;

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
