<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Executor;

use HelgeSverre\Synapse\Provider\Response\UsageInfo;
use HelgeSverre\Synapse\State\ConversationState;

/**
 * Result from a streaming execution after collecting all events.
 */
final readonly class StreamingResult
{
    public function __construct(
        public string $text,
        public ?string $finishReason = null,
        public ?UsageInfo $usage = null,
        public ?ConversationState $state = null,
    ) {}
}
