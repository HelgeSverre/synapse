<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Executor;

use HelgeSverre\Synapse\Provider\Request\ToolDefinition;
use HelgeSverre\Synapse\State\ConversationState;

final readonly class MiddlewareToolExecutor implements ToolExecutorInterface
{
    /** @var list<ToolMiddleware> */
    private array $middleware;

    /**
     * @param  list<ToolMiddleware>  $middleware
     */
    public function __construct(
        private ToolExecutorInterface $inner,
        array $middleware = [],
    ) {
        $this->middleware = $middleware;
    }

    public function withMiddleware(ToolMiddleware ...$middleware): self
    {
        $combined = [...$this->middleware, ...$middleware];

        return new self($this->inner, array_values($combined));
    }

    /** @return list<ToolDefinition> */
    public function getToolDefinitions(): array
    {
        return $this->inner->getToolDefinitions();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function callFunctionResult(string $name, array $input, ?ConversationState $state = null): ToolResult
    {
        $invocation = new ToolInvocation($name, $input, $state);

        $next = function (ToolInvocation $current): ToolResult {
            return $this->inner->callFunctionResult($current->name, $current->input, $current->state);
        };

        foreach (array_reverse($this->middleware) as $middleware) {
            $previous = $next;
            $next = fn (ToolInvocation $current): ToolResult => $middleware->handle($current, $previous);
        }

        return $next($invocation);
    }
}
