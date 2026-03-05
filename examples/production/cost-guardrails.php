<?php

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createExecutor;
use function HelgeSverre\Synapse\createParser;

use HelgeSverre\Synapse\Options\ExecutorOptions;

use function HelgeSverre\Synapse\useLlm;

$budgetTokens = 2_000;
$usedTokens = 0;

$primary = useLlm('openai', ['apiKey' => getenv('OPENAI_API_KEY'), 'model' => 'gpt-4o-mini']);
$fallback = useLlm('openai', ['apiKey' => getenv('OPENAI_API_KEY'), 'model' => 'gpt-4o-mini']);

function runWithModel($llm, string $question): \HelgeSverre\Synapse\Executor\ExecutionResult
{
    $executor = createExecutor(new ExecutorOptions(
        llm: $llm,
        prompt: createChatPrompt()->addUserMessage('{{question}}', parseTemplate: true),
        parser: createParser('string'),
        maxTokens: 300,
    ));

    return $executor->run(['question' => $question]);
}

$result = runWithModel($primary, 'Give a concise summary of CAP theorem');
$usedTokens += $result->response?->usage?->getTotal() ?? 0;

if ($usedTokens > $budgetTokens) {
    throw new RuntimeException('Token budget exceeded');
}

if ($usedTokens > (int) ($budgetTokens * 0.8)) {
    $result = runWithModel($fallback, 'Continue with a shorter explanation in one sentence');
}

echo $result->getValue()."\n";
