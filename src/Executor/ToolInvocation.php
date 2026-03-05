<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Executor;

use HelgeSverre\Synapse\State\ConversationState;

final readonly class ToolInvocation
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public string $name,
        public array $input,
        public ?ConversationState $state = null,
    ) {}
}
