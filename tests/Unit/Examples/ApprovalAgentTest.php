<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Examples;

require_once __DIR__.'/../../../examples/approval-agent/ApprovalRequest.php';
require_once __DIR__.'/../../../examples/approval-agent/ApprovalDecision.php';
require_once __DIR__.'/../../../examples/approval-agent/ApprovalProviderInterface.php';
require_once __DIR__.'/../../../examples/approval-agent/ApprovingToolRegistry.php';
require_once __DIR__.'/../../../examples/approval-agent/RiskyTools.php';

use HelgeSverre\Synapse\Examples\ApprovalAgent\ApprovalAction;
use HelgeSverre\Synapse\Examples\ApprovalAgent\ApprovalDecision;
use HelgeSverre\Synapse\Examples\ApprovalAgent\ApprovalProviderInterface;
use HelgeSverre\Synapse\Examples\ApprovalAgent\ApprovalRequest;
use HelgeSverre\Synapse\Examples\ApprovalAgent\ApprovingToolRegistry;
use HelgeSverre\Synapse\Examples\ApprovalAgent\RiskyTools;
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

        $tools = new ApprovingToolRegistry(
            [RiskyTools::readFile()],
            $mockProvider,
            minimumRiskForApproval: 'medium',
        );

        $result = $tools->callFunctionResult('read_file', ['path' => 'test.txt']);

        $this->assertSame(0, $mockProvider->callCount); // No approval requested
        $this->assertTrue($result->success);
        $this->assertIsString($result->result);
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

        $tools = new ApprovingToolRegistry(
            [RiskyTools::deleteFiles()],
            $mockProvider,
            minimumRiskForApproval: 'medium',
        );

        $result = $tools->callFunctionResult('delete_files', ['pattern' => '*.tmp']);

        $this->assertSame(1, $mockProvider->callCount); // Approval was requested
        $this->assertTrue($result->success);
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

        $tools = new ApprovingToolRegistry(
            [RiskyTools::deleteFiles()],
            $mockProvider,
        );

        $result = $tools->callFunctionResult('delete_files', ['pattern' => '*.tmp']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Action rejected by user', implode(' | ', $result->errors));
        $this->assertStringContainsString('Not allowed', implode(' | ', $result->errors));
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

        $tools = new ApprovingToolRegistry(
            [RiskyTools::deleteFiles()],
            $mockProvider,
        );

        $result = $tools->callFunctionResult('delete_files', ['pattern' => '*.tmp']);

        // The edited pattern should be used
        $this->assertTrue($result->success);
        $this->assertIsString($result->result);
        $payload = json_decode($result->result, true);
        $this->assertIsArray($payload);
        $this->assertSame('safe-only.txt', $payload['pattern']);
    }
}
