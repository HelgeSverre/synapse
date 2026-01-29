<?php

declare(strict_types=1);

namespace LlmExe\Examples\ApprovalAgent;

use LlmExe\Executor\CallableExecutor;
use LlmExe\Executor\ToolExecutorInterface;
use LlmExe\Executor\UseExecutors;
use LlmExe\Provider\Request\ToolDefinition;
use LlmExe\State\ConversationState;

/**
 * Decorator that intercepts tool calls and requires approval for risky tools.
 *
 * Risk is determined by tool attributes:
 * - No 'risk' attribute or 'risk' => 'low': no approval needed
 * - 'risk' => 'medium': approval requested
 * - 'risk' => 'high' or 'critical': approval required
 */
final class ApprovingUseExecutors implements ToolExecutorInterface
{
    private UseExecutors $inner;

    /** @var array<string, string> Tool name => risk level */
    private array $riskLevels = [];

    /** @var array<string, string> Tool name => description */
    private array $descriptions = [];

    /**
     * @param  list<CallableExecutor>  $executors
     */
    public function __construct(
        array $executors,
        private readonly ApprovalProviderInterface $approvalProvider,
        private readonly string $minimumRiskForApproval = 'medium',
    ) {
        $this->inner = new UseExecutors($executors);

        // Extract risk levels from executor attributes
        foreach ($executors as $executor) {
            $name = $executor->getName();
            $attrs = $executor->getAttributes();
            $this->riskLevels[$name] = $attrs['risk'] ?? 'low';
            $this->descriptions[$name] = $attrs['description'] ?? $executor->getDescription();
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function callFunction(string $name, array $input, ?ConversationState $state = null): mixed
    {
        $riskLevel = $this->riskLevels[$name] ?? 'low';

        if ($this->requiresApproval($riskLevel)) {
            $request = new ApprovalRequest(
                toolName: $name,
                arguments: $input,
                riskLevel: $riskLevel,
                description: $this->descriptions[$name] ?? '',
            );

            $decision = $this->approvalProvider->requestApproval($request);

            return match ($decision->action) {
                ApprovalAction::Approve => $this->inner->callFunction($name, $input, $state),
                ApprovalAction::Edit => $this->inner->callFunction($name, $decision->editedArguments ?? $input, $state),
                ApprovalAction::Reject => json_encode([
                    'error' => 'Action rejected by user',
                    'reason' => $decision->reason,
                    'tool' => $name,
                ]),
            };
        }

        return $this->inner->callFunction($name, $input, $state);
    }

    private function requiresApproval(string $riskLevel): bool
    {
        $levels = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $toolLevel = $levels[$riskLevel] ?? 1;
        $minLevel = $levels[$this->minimumRiskForApproval] ?? 2;

        return $toolLevel >= $minLevel;
    }

    /** @return list<ToolDefinition> */
    public function getToolDefinitions(): array
    {
        return $this->inner->getToolDefinitions();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<ToolDefinition>
     */
    public function getVisibleToolDefinitions(array $input = [], ?ConversationState $state = null): array
    {
        return $this->inner->getVisibleToolDefinitions($input, $state);
    }

    public function hasFunction(string $name): bool
    {
        return $this->inner->hasFunction($name);
    }

    public function getFunction(string $name): ?CallableExecutor
    {
        return $this->inner->getFunction($name);
    }

    /** @return list<CallableExecutor> */
    public function getFunctions(): array
    {
        return $this->inner->getFunctions();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<CallableExecutor>
     */
    public function getVisibleFunctions(array $input = [], ?ConversationState $state = null): array
    {
        return $this->inner->getVisibleFunctions($input, $state);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{valid: bool, errors: list<string>}
     */
    public function validateFunctionInput(string $name, array $input): array
    {
        return $this->inner->validateFunctionInput($name, $input);
    }

    public function register(CallableExecutor $executor): self
    {
        $this->inner->register($executor);
        $name = $executor->getName();
        $attrs = $executor->getAttributes();
        $this->riskLevels[$name] = $attrs['risk'] ?? 'low';
        $this->descriptions[$name] = $attrs['description'] ?? $executor->getDescription();

        return $this;
    }
}
