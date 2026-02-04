<?php

// examples/approval-agent/ApprovalRequest.php
declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\ApprovalAgent;

/**
 * Represents a request for human approval before tool execution.
 */
final readonly class ApprovalRequest
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public string $toolName,
        public array $arguments,
        public string $riskLevel, // 'low', 'medium', 'high', 'critical'
        public string $description,
    ) {}

    public function format(): string
    {
        $argsJson = json_encode($this->arguments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<TEXT
        Tool: {$this->toolName}
        Risk: {$this->riskLevel}
        Description: {$this->description}
        Arguments:
        {$argsJson}
        TEXT;
    }
}
