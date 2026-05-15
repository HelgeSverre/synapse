<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Examples;

require_once __DIR__.'/../../../examples/router-agent/AgentDefinition.php';
require_once __DIR__.'/../../../examples/router-agent/AgentRegistry.php';

use HelgeSverre\Synapse\Examples\RouterAgent\AgentDefinition;
use HelgeSverre\Synapse\Examples\RouterAgent\AgentRegistry;
use PHPUnit\Framework\TestCase;

final class RouterAgentTest extends TestCase
{
    public function test_agent_definition_creates_tool_description(): void
    {
        $agent = new AgentDefinition(
            name: 'test_agent',
            description: 'A test agent',
            systemPrompt: 'You are a test.',
            capabilities: ['testing', 'mocking'],
        );

        $desc = $agent->toToolDescription();

        $this->assertStringContainsString('A test agent', $desc);
        $this->assertStringContainsString('testing', $desc);
        $this->assertStringContainsString('mocking', $desc);
    }

    public function test_registry_registers_and_retrieves_agents(): void
    {
        $registry = new AgentRegistry;

        $agent = new AgentDefinition(
            name: 'my_agent',
            description: 'Test',
            systemPrompt: 'Test',
            capabilities: ['test'],
        );

        $registry->register($agent);

        $this->assertSame($agent, $registry->get('my_agent'));
        $this->assertNull($registry->get('unknown'));
    }

    public function test_registry_lists_agent_names(): void
    {
        $registry = new AgentRegistry;

        $registry->register(new AgentDefinition('agent1', 'Desc', 'Prompt', ['cap']));
        $registry->register(new AgentDefinition('agent2', 'Desc', 'Prompt', ['cap']));

        $names = $registry->names();

        $this->assertContains('agent1', $names);
        $this->assertContains('agent2', $names);
    }

    public function test_default_registry_has_specialists(): void
    {
        $registry = AgentRegistry::withDefaults();

        $names = $registry->names();

        $this->assertContains('code_reviewer', $names);
        $this->assertContains('security_auditor', $names);
        $this->assertContains('researcher', $names);
        $this->assertContains('documenter', $names);
    }

    public function test_registry_generates_manager_description(): void
    {
        $registry = AgentRegistry::withDefaults();

        $desc = $registry->getDescriptionForManager();

        $this->assertStringContainsString('Available specialist agents', $desc);
        $this->assertStringContainsString('code_reviewer', $desc);
        $this->assertStringContainsString('security_auditor', $desc);
    }
}
