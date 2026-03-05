<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Executor;

use HelgeSverre\Synapse\Provider\Request\ToolDefinition;
use HelgeSverre\Synapse\State\ConversationState;

interface ToolCatalogResolver
{
    /**
     * @param  array<string, mixed>  $input
     * @return list<ToolDefinition>
     */
    public function resolve(array $input, ConversationState $state, int $iteration, ToolExecutorInterface $tools): array;
}
