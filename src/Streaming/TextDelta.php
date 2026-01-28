<?php

declare(strict_types=1);

namespace LlmExe\Streaming;

/**
 * A chunk of text content from the stream.
 */
final readonly class TextDelta implements StreamEvent
{
    public function __construct(
        public string $text,
    ) {}
}
