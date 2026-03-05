<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Agent;

use HelgeSverre\Synapse\Llm;
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\State\ConversationState;

final class AgentRegistry
{
    /** @var array<string, AgentDefinition> */
    private array $definitions = [];

    public function register(AgentDefinition $definition): self
    {
        $this->definitions[$definition->name] = $definition;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    public function get(string $name): AgentDefinition
    {
        return $this->definitions[$name] ?? throw new \InvalidArgumentException("Unknown agent: {$name}");
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->definitions);
    }

    /** @return list<AgentDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function createRuntime(string $name, LlmProviderInterface|Llm $llm, ?ConversationState $state = null): AgentRuntime
    {
        return new AgentRuntime($llm, $this->get($name), $state);
    }
}
