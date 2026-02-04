<?php

// examples/approval-agent/ApprovalDecision.php
declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\ApprovalAgent;

enum ApprovalAction: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case Edit = 'edit';
}

/**
 * Represents the human's decision on an approval request.
 */
final readonly class ApprovalDecision
{
    /**
     * @param  array<string, mixed>|null  $editedArguments
     */
    public function __construct(
        public ApprovalAction $action,
        public ?string $reason = null,
        public ?array $editedArguments = null,
    ) {}

    public static function approve(): self
    {
        return new self(ApprovalAction::Approve);
    }

    public static function reject(string $reason): self
    {
        return new self(ApprovalAction::Reject, $reason);
    }

    /**
     * @param  array<string, mixed>  $newArguments
     */
    public static function edit(array $newArguments): self
    {
        return new self(ApprovalAction::Edit, null, $newArguments);
    }
}
