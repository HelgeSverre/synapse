<?php

declare(strict_types=1);

namespace LlmExe\Hooks\Events;

use LlmExe\State\Message;

final readonly class AfterPromptRender
{
    /** @param string|list<Message> $rendered */
    public function __construct(
        public string|array $rendered,
    ) {}
}
