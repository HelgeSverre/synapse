<?php

declare(strict_types=1);

namespace LlmExe\Hooks\Events;

use LlmExe\Prompt\PromptInterface;

final readonly class BeforePromptRender
{
    /** @param array<string, mixed> $values */
    public function __construct(
        public PromptInterface $prompt,
        public array $values,
    ) {}
}
