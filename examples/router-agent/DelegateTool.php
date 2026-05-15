<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\RouterAgent;

use HelgeSverre\Synapse\Executor\CallableExecutor;
use HelgeSverre\Synapse\Streaming\StreamableProviderInterface;

/**
 * Creates a delegate tool that the manager can use to invoke specialist agents.
 *
 * NOTE: This is a synchronous version for simplicity.
 * The streaming version requires yielding from within tool execution,
 * which would need executor changes.
 */
final class DelegateTool
{
    /** @var array<string, string> */
    private array $lastResults = [];

    public function __construct(
        private readonly StreamableProviderInterface $provider,
        private readonly string $model,
        private readonly AgentRegistry $registry,
        private readonly ?\Closure $onAgentEvent = null,
    ) {}

    public function create(): CallableExecutor
    {
        $agentNames = $this->registry->names();
        $agentDescriptions = [];

        foreach ($this->registry->all() as $agent) {
            $agentDescriptions[$agent->name] = $agent->toToolDescription();
        }

        return new CallableExecutor(
            name: 'delegate',
            description: 'Delegate a task to a specialist agent. Available agents: '.implode(', ', $agentNames),
            handler: function (array $args) use ($agentDescriptions): string {
                $agentName = $args['agent'] ?? '';
                $task = $args['task'] ?? '';

                if (! isset($agentDescriptions[$agentName])) {
                    return json_encode([
                        'error' => "Unknown agent: {$agentName}",
                        'available_agents' => array_keys($agentDescriptions),
                    ]);
                }

                $runner = new AgentRunner($this->provider, $this->model, $this->registry);
                $output = '';

                foreach ($runner->run($agentName, $task) as $event) {
                    if ($this->onAgentEvent !== null) {
                        ($this->onAgentEvent)($event);
                    }

                    if ($event->type === 'completed') {
                        $output = $event->content;
                    } elseif ($event->type === 'error') {
                        return json_encode(['error' => $event->content]);
                    }
                }

                $this->lastResults[$agentName] = $output;

                return json_encode([
                    'agent' => $agentName,
                    'output' => $output,
                ]);
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'agent' => [
                        'type' => 'string',
                        'enum' => $agentNames,
                        'description' => 'Name of the specialist agent to delegate to',
                    ],
                    'task' => [
                        'type' => 'string',
                        'description' => 'The task or question for the specialist agent',
                    ],
                ],
                'required' => ['agent', 'task'],
            ],
        );
    }

    /** @return array<string, string> */
    public function getLastResults(): array
    {
        return $this->lastResults;
    }
}
