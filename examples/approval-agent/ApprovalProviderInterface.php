<?php

// examples/approval-agent/ApprovalProviderInterface.php
declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\ApprovalAgent;

/**
 * Interface for requesting human approval.
 * Implementations may be CLI-based, web-based, or queue-based.
 */
interface ApprovalProviderInterface
{
    /**
     * Request approval from a human. Blocks until decision is made.
     */
    public function requestApproval(ApprovalRequest $request): ApprovalDecision;
}
