<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Executor;

use HelgeSverre\Synapse\Provider\Request\ToolDefinition;
use HelgeSverre\Synapse\State\ConversationState;

/**
 * Interface for tool executors that can be used with LLM executors.
 */
interface ToolExecutorInterface
{
    /**
     * @return list<ToolDefinition>
     */
    public function getToolDefinitions(): array;

    /**
     * @param  array<string, mixed>  $input
     */
    public function callFunction(string $name, array $input, ?ConversationState $state = null): mixed;
}
