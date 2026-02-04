<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Hooks\Events;

use HelgeSverre\Synapse\Prompt\PromptInterface;

final readonly class BeforePromptRender
{
    /** @param array<string, mixed> $values */
    public function __construct(
        public PromptInterface $prompt,
        public array $values,
    ) {}
}
