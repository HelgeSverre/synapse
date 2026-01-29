<?php

// examples/approval-agent/CliApprovalProvider.php
declare(strict_types=1);

namespace LlmExe\Examples\ApprovalAgent;

/**
 * CLI-based approval provider that prompts via STDIN.
 */
final class CliApprovalProvider implements ApprovalProviderInterface
{
    private const YELLOW = "\033[33m";

    private const RED = "\033[31m";

    private const GREEN = "\033[32m";

    private const CYAN = "\033[36m";

    private const RESET = "\033[0m";

    private const BOLD = "\033[1m";

    public function requestApproval(ApprovalRequest $request): ApprovalDecision
    {
        $riskColor = match ($request->riskLevel) {
            'critical' => self::RED,
            'high' => self::RED,
            'medium' => self::YELLOW,
            default => self::CYAN,
        };

        echo "\n".self::BOLD.$riskColor.'⚠️  APPROVAL REQUIRED'.self::RESET."\n";
        echo str_repeat('─', 50)."\n";
        echo self::CYAN.'Tool: '.self::RESET.$request->toolName."\n";
        echo self::CYAN.'Risk: '.self::RESET.$riskColor.strtoupper($request->riskLevel).self::RESET."\n";
        echo self::CYAN.'Description: '.self::RESET.$request->description."\n";
        echo self::CYAN.'Arguments: '.self::RESET."\n";

        $argsJson = json_encode($request->arguments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        foreach (explode("\n", $argsJson) as $line) {
            echo '  '.$line."\n";
        }

        echo str_repeat('─', 50)."\n";
        echo self::BOLD.'[y]'.self::RESET.' Approve  ';
        echo self::BOLD.'[n]'.self::RESET.' Reject  ';
        echo self::BOLD.'[e]'.self::RESET." Edit\n";
        echo '> ';

        $input = strtolower(trim(fgets(STDIN) ?: 'n'));

        return match ($input) {
            'y', 'yes' => ApprovalDecision::approve(),
            'e', 'edit' => $this->handleEdit($request),
            default => $this->handleReject(),
        };
    }

    private function handleReject(): ApprovalDecision
    {
        echo 'Reason for rejection (optional): ';
        $reason = trim(fgets(STDIN) ?: '');

        return ApprovalDecision::reject($reason ?: 'User rejected the action');
    }

    private function handleEdit(ApprovalRequest $request): ApprovalDecision
    {
        echo "Enter new arguments as JSON (or press Enter to cancel):\n";
        $json = trim(fgets(STDIN) ?: '');

        if ($json === '') {
            return ApprovalDecision::reject('User cancelled edit');
        }

        $newArgs = json_decode($json, true);
        if (! is_array($newArgs)) {
            echo self::RED.'Invalid JSON. Rejecting.'.self::RESET."\n";

            return ApprovalDecision::reject('Invalid JSON provided for edit');
        }

        return ApprovalDecision::edit($newArgs);
    }
}
