<?php

declare(strict_types=1);

namespace LlmExe\Hooks\Events;

use LlmExe\Provider\Request\ToolCall;

final readonly class OnToolCall
{
    public function __construct(
        public ToolCall $toolCall,
    ) {}
}
