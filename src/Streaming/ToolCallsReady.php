<?php

declare(strict_types=1);

namespace LlmExe\Streaming;

use LlmExe\Provider\Request\ToolCall;

/**
 * Emitted when tool calls are complete and ready for execution.
 */
final readonly class ToolCallsReady implements StreamEvent
{
    /**
     * @param  list<ToolCall>  $toolCalls
     */
    public function __construct(
        public array $toolCalls,
    ) {}
}
