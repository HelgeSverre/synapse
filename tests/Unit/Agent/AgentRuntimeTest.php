<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Agent;

use HelgeSverre\Synapse\Agent\AgentDefinition;
use HelgeSverre\Synapse\Agent\AgentRegistry;
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\State\Message;
use PHPUnit\Framework\TestCase;

final class AgentFakeProvider implements LlmProviderInterface
{
    public function generate(GenerationRequest $request): GenerationResponse
    {
        return new GenerationResponse(
            text: 'agent-output',
            messages: [Message::assistant('agent-output')],
            toolCalls: [],
            model: $request->model,
        );
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities;
    }

    public function getName(): string
    {
        return 'agent-fake';
    }
}

final class AgentRuntimeTest extends TestCase
{
    public function test_agent_registry_creates_runtime_and_runs_agent(): void
    {
        $registry = new AgentRegistry;
        $registry->register(new AgentDefinition(
            name: 'assistant',
            prompt: \HelgeSverre\Synapse\Factory::createTextPrompt()->setContent('Question: {{q}}'),
            model: 'test-model',
            parser: \HelgeSverre\Synapse\Factory::createParser('string'),
        ));

        $runtime = $registry->createRuntime('assistant', new AgentFakeProvider);
        $result = $runtime->run(['q' => 'hello']);

        $this->assertSame('agent-output', $result->getValue());
        $this->assertTrue($registry->has('assistant'));
        $this->assertContains('assistant', $registry->names());
    }
}
