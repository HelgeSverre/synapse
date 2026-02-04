<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Hooks\Events;

use HelgeSverre\Synapse\State\Message;

final readonly class AfterPromptRender
{
    /** @param string|list<Message> $rendered */
    public function __construct(
        public string|array $rendered,
    ) {}
}
