<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Workflow;

use HelgeSverre\Synapse\State\ConversationState;

final readonly class WorkflowResult
{
    /**
     * @param  array<string, WorkflowStepResult>  $steps
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public bool $success,
        public array $steps,
        public array $data,
        public ConversationState $state,
    ) {}

    public function getStep(string $name): ?WorkflowStepResult
    {
        return $this->steps[$name] ?? null;
    }

    public function getData(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
