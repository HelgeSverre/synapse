<?php

declare(strict_types=1);

namespace LlmExe\Streaming;

/**
 * A partial tool call delta from the stream.
 * Used internally for accumulation; not typically exposed to consumers.
 */
final readonly class ToolCallDelta implements StreamEvent
{
    public function __construct(
        public int $index,
        public ?string $id = null,
        public ?string $name = null,
        public ?string $arguments = null,
    ) {}
}
