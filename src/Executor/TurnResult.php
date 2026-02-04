<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Executor;

use HelgeSverre\Synapse\Provider\Request\ToolCall;
use HelgeSverre\Synapse\Provider\Response\UsageInfo;

/**
 * Result from a single streaming turn (model call).
 *
 * Used internally by StreamingLlmExecutorWithFunctions to track
 * what happened during one iteration of the tool loop.
 *
 * @internal
 */
final readonly class TurnResult
{
    /**
     * @param  list<ToolCall>  $toolCalls
     */
    public function __construct(
        public string $assistantText,
        public array $toolCalls,
        public ?string $finishReason,
        public ?UsageInfo $usage,
    ) {}
}
