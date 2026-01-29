<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Examples;

require_once __DIR__.'/../../../examples/approval-agent/ApprovalRequest.php';
require_once __DIR__.'/../../../examples/approval-agent/ApprovalDecision.php';
require_once __DIR__.'/../../../examples/approval-agent/ApprovalProviderInterface.php';
require_once __DIR__.'/../../../examples/approval-agent/ApprovingUseExecutors.php';
require_once __DIR__.'/../../../examples/approval-agent/RiskyTools.php';

use LlmExe\Examples\ApprovalAgent\ApprovalAction;
use LlmExe\Examples\ApprovalAgent\ApprovalDecision;
use LlmExe\Examples\ApprovalAgent\ApprovalProviderInterface;
use LlmExe\Examples\ApprovalAgent\ApprovalRequest;
use LlmExe\Examples\ApprovalAgent\ApprovingUseExecutors;
use LlmExe\Examples\ApprovalAgent\RiskyTools;
use PHPUnit\Framework\TestCase;

final class ApprovalAgentTest extends TestCase
{
    public function test_approval_request_formats_correctly(): void
    {
        $request = new ApprovalRequest(
            toolName: 'delete_files',
            arguments: ['pattern' => '*.tmp'],
            riskLevel: 'high',
            description: 'Permanently deletes files',
        );

        $formatted = $request->format();
        $this->assertStringContainsString('delete_files', $formatted);
        $this->assertStringContainsString('high', $formatted);
    }

    public function test_approval_decision_factory_methods(): void
    {
        $approve = ApprovalDecision::approve();
        $this->assertSame(ApprovalAction::Approve, $approve->action);

        $reject = ApprovalDecision::reject('Too risky');
        $this->assertSame(ApprovalAction::Reject, $reject->action);
        $this->assertSame('Too risky', $reject->reason);

        $edit = ApprovalDecision::edit(['pattern' => 'safe.txt']);
        $this->assertSame(ApprovalAction::Edit, $edit->action);
        $this->assertSame(['pattern' => 'safe.txt'], $edit->editedArguments);
    }

    public function test_low_risk_tools_execute_without_approval(): void
    {
        $mockProvider = new class implements ApprovalProviderInterface
        {
            public int $callCount = 0;

            public function requestApproval(ApprovalRequest $request): ApprovalDecision
            {
                $this->callCount++;

                return ApprovalDecision::approve();
            }
        };

        $tools = new ApprovingUseExecutors(
            [RiskyTools::readFile()],
            $mockProvider,
            minimumRiskForApproval: 'medium',
        );

        $result = $tools->callFunction('read_file', ['path' => 'test.txt']);

        $this->assertSame(0, $mockProvider->callCount); // No approval requested
        $this->assertIsString($result);
    }

    public function test_high_risk_tools_require_approval(): void
    {
        $mockProvider = new class implements ApprovalProviderInterface
        {
            public int $callCount = 0;

            public function requestApproval(ApprovalRequest $request): ApprovalDecision
            {
                $this->callCount++;

                return ApprovalDecision::approve();
            }
        };

        $tools = new ApprovingUseExecutors(
            [RiskyTools::deleteFiles()],
            $mockProvider,
            minimumRiskForApproval: 'medium',
        );

        $result = $tools->callFunction('delete_files', ['pattern' => '*.tmp']);

        $this->assertSame(1, $mockProvider->callCount); // Approval was requested
    }

    public function test_rejected_tool_returns_error(): void
    {
        $mockProvider = new class implements ApprovalProviderInterface
        {
            public function requestApproval(ApprovalRequest $request): ApprovalDecision
            {
                return ApprovalDecision::reject('Not allowed');
            }
        };

        $tools = new ApprovingUseExecutors(
            [RiskyTools::deleteFiles()],
            $mockProvider,
        );

        $result = $tools->callFunction('delete_files', ['pattern' => '*.tmp']);
        $decoded = json_decode($result, true);

        $this->assertSame('Action rejected by user', $decoded['error']);
        $this->assertSame('Not allowed', $decoded['reason']);
    }

    public function test_edited_tool_uses_new_arguments(): void
    {
        $mockProvider = new class implements ApprovalProviderInterface
        {
            public function requestApproval(ApprovalRequest $request): ApprovalDecision
            {
                return ApprovalDecision::edit(['pattern' => 'safe-only.txt']);
            }
        };

        $tools = new ApprovingUseExecutors(
            [RiskyTools::deleteFiles()],
            $mockProvider,
        );

        $result = $tools->callFunction('delete_files', ['pattern' => '*.tmp']);
        $decoded = json_decode($result, true);

        // The edited pattern should be used
        $this->assertSame('safe-only.txt', $decoded['pattern']);
    }
}
