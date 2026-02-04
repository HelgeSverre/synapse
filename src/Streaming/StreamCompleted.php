<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Streaming;

use HelgeSverre\Synapse\Provider\Response\UsageInfo;

/**
 * Emitted when the stream is complete.
 */
final readonly class StreamCompleted implements StreamEvent
{
    public function __construct(
        public ?string $finishReason = null,
        public ?UsageInfo $usage = null,
    ) {}
}
