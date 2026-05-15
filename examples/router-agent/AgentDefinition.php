<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\RouterAgent;

use HelgeSverre\Synapse\Executor\ToolRegistry;

/**
 * Definition of a specialist agent.
 */
final readonly class AgentDefinition
{
    /**
     * @param  list<string>  $capabilities
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $systemPrompt,
        public array $capabilities,
        public ?ToolRegistry $tools = null,
    ) {}

    public function toToolDescription(): string
    {
        $caps = implode(', ', $this->capabilities);

        return "{$this->description}. Capabilities: {$caps}";
    }
}
