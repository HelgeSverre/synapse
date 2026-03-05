<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Agent;

use HelgeSverre\Synapse\Executor\ToolCatalogResolver;
use HelgeSverre\Synapse\Executor\ToolExecutorInterface;
use HelgeSverre\Synapse\Hooks\HookDispatcherInterface;
use HelgeSverre\Synapse\Parser\ParserInterface;
use HelgeSverre\Synapse\Prompt\PromptInterface;

final readonly class AgentDefinition
{
    public function __construct(
        public string $name,
        public PromptInterface $prompt,
        public string $model,
        public ?ParserInterface $parser = null,
        public ?ToolExecutorInterface $tools = null,
        public bool $stream = false,
        public int $maxIterations = 10,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?HookDispatcherInterface $hooks = null,
        public ?ToolCatalogResolver $toolCatalogResolver = null,
    ) {}
}
