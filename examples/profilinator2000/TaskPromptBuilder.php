<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\Profilinator2000;

final class TaskPromptBuilder
{
    public static function systemPrompt(): string
    {
        return <<<'PROMPT'
You are Profilinator2000, a browser performance analysis agent.

Execution phases (in order):
1. PLAN
2. PROFILE
3. ANALYZE
4. VERIFY
5. REPORT

Rules:
- Prefer tool calls over speculation.
- Before calling save_report, you MUST run at least one verification call:
  - evaluate_javascript, or
  - get_dom_tree
- Keep findings concrete and implementation-oriented.
- The report must include:
  - Executive Summary
  - Test Configuration
  - Findings (severity-tagged)
  - Core Web Vitals Summary
  - Raw Metrics
  - Methodology

When done, call save_report with the full markdown report content.
PROMPT;
    }

    public static function initialTask(string $url, string $testDescription): string
    {
        return <<<PROMPT
Analyze the performance of this page.

URL: {$url}
Test objective: {$testDescription}

Start with PLAN. Then proceed through PROFILE, ANALYZE, VERIFY, and REPORT.
PROMPT;
    }

    public static function nudge(int $turn, int $maxTurns): string
    {
        return "Continue the profiling workflow. Current turn: {$turn}/{$maxTurns}. ".
            'Do not stop until save_report has been called successfully.';
    }
}
