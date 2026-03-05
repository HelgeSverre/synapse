<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Workflow;

use HelgeSverre\Synapse\State\ConversationState;

final readonly class WorkflowStep
{
    /** @var \Closure(array<string, mixed>, ConversationState): mixed */
    public \Closure $handler;

    /** @var (\Closure(array<string, mixed>, array<string, WorkflowStepResult>): bool)|null */
    public ?\Closure $when;

    /**
     * @param  callable(array<string, mixed>, ConversationState): mixed  $handler
     * @param  list<string>  $dependsOn
     * @param  callable(array<string, mixed>, array<string, WorkflowStepResult>): bool|null  $when
     */
    public function __construct(
        public string $name,
        callable $handler,
        public array $dependsOn = [],
        ?callable $when = null,
        public WorkflowRetryPolicy $retryPolicy = new WorkflowRetryPolicy,
        public bool $continueOnError = false,
    ) {
        $this->handler = \Closure::fromCallable($handler);
        $this->when = $when !== null ? \Closure::fromCallable($when) : null;
    }
}
