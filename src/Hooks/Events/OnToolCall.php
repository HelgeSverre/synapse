<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Hooks\Events;

use HelgeSverre\Synapse\Provider\Request\ToolCall;

final readonly class OnToolCall
{
    public function __construct(
        public ToolCall $toolCall,
    ) {}
}
