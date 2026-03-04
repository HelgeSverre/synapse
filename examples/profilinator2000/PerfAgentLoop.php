<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\Profilinator2000;

use HelgeSverre\Synapse\Executor\StreamingLlmExecutorWithFunctions;
use HelgeSverre\Synapse\Executor\ToolExecutorInterface;
use HelgeSverre\Synapse\Prompt\ChatPrompt;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\StreamableProviderInterface;

final class PerfAgentLoop
{
    public function __construct(
        private readonly StreamableProviderInterface $provider,
        private readonly ToolExecutorInterface $tools,
        private readonly ReportWriter $reportWriter,
        private readonly string $model,
        private readonly int $maxToolIterations = 12,
    ) {}

    public function run(string $url, string $testDescription, int $maxTurns = 8): RunResult
    {
        $prompt = $this->createPrompt();
        $executor = new StreamingLlmExecutorWithFunctions(
            provider: $this->provider,
            prompt: $prompt,
            model: $this->model,
            tools: $this->tools,
            maxIterations: $this->maxToolIterations,
        );

        /** @var list<Message> $history */
        $history = [];

        for ($turn = 1; $turn <= $maxTurns; $turn++) {
            $message = $turn === 1
                ? TaskPromptBuilder::initialTask($url, $testDescription)
                : TaskPromptBuilder::nudge($turn, $maxTurns);

            $result = $executor->streamAndCollect([
                '_dialogueKey' => 'history',
                'history' => $history,
                'message' => $message,
            ]);

            $history[] = Message::user($message);

            if ($result->text !== '') {
                $history[] = Message::assistant($result->text);
            }

            if ($this->reportWriter->hasSavedReport()) {
                return new RunResult(
                    success: true,
                    turns: $turn,
                    reportPath: $this->reportWriter->getSavedPath(),
                );
            }
        }

        return new RunResult(
            success: false,
            turns: $maxTurns,
            reportPath: $this->reportWriter->getSavedPath(),
        );
    }

    private function createPrompt(): ChatPrompt
    {
        return (new ChatPrompt)
            ->addSystemMessage(TaskPromptBuilder::systemPrompt())
            ->addHistoryPlaceholder('history')
            ->addUserMessage('{{message}}', parseTemplate: true);
    }
}
