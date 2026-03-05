<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Executor;

use HelgeSverre\Synapse\Provider\Request\ToolDefinition;
use HelgeSverre\Synapse\State\ConversationState;

/**
 * Registry/manager for multiple CallableExecutors (tools).
 */
final class ToolRegistry implements ToolExecutorInterface
{
    /** @var array<string, CallableExecutor> */
    private array $executors = [];

    /** @param list<CallableExecutor> $executors */
    public function __construct(array $executors = [])
    {
        foreach ($executors as $executor) {
            $this->register($executor);
        }
    }

    public function register(CallableExecutor $executor): self
    {
        $this->executors[$executor->getName()] = $executor;

        return $this;
    }

    public function hasFunction(string $name): bool
    {
        return isset($this->executors[$name]);
    }

    public function getFunction(string $name): ?CallableExecutor
    {
        return $this->executors[$name] ?? null;
    }

    /** @return list<CallableExecutor> */
    public function getFunctions(): array
    {
        return array_values($this->executors);
    }

    /**
     * Get functions visible for the given context.
     *
     * @param  array<string, mixed>  $input
     * @return list<CallableExecutor>
     */
    public function getVisibleFunctions(array $input = [], ?ConversationState $state = null): array
    {
        $visible = [];
        foreach ($this->executors as $executor) {
            if ($executor->isVisible($input, $state)) {
                $visible[] = $executor;
            }
        }

        return $visible;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function callFunctionResult(string $name, array $input, ?ConversationState $state = null): ToolResult
    {
        $executor = $this->executors[$name] ?? null;

        if ($executor === null) {
            return ToolResult::failure(["Unknown function: {$name}"]);
        }

        return $executor->execute($input, $state);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{valid: bool, errors: list<string>}
     */
    public function validateFunctionInput(string $name, array $input): array
    {
        $executor = $this->executors[$name] ?? null;

        if ($executor === null) {
            return ['valid' => false, 'errors' => ["Unknown function: {$name}"]];
        }

        return $executor->validateInput($input);
    }

    /** @return list<ToolDefinition> */
    public function getToolDefinitions(): array
    {
        return array_map(
            fn (CallableExecutor $e): ToolDefinition => $e->toToolDefinition(),
            array_values($this->executors),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<ToolDefinition>
     */
    public function getVisibleToolDefinitions(array $input = [], ?ConversationState $state = null): array
    {
        return array_map(
            fn (CallableExecutor $e): ToolDefinition => $e->toToolDefinition(),
            $this->getVisibleFunctions($input, $state),
        );
    }
}
