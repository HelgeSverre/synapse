<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createLlmExecutor;
use function HelgeSverre\Synapse\createParser;

use HelgeSverre\Synapse\Hooks\Events\AfterProviderCall;
use HelgeSverre\Synapse\Hooks\Events\BeforeProviderCall;
use HelgeSverre\Synapse\Hooks\Events\OnComplete;
use HelgeSverre\Synapse\Hooks\Events\OnError;
use HelgeSverre\Synapse\Hooks\Events\OnSuccess;

use function HelgeSverre\Synapse\useLlm;

// Assume transport is configured...

$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);

$prompt = createChatPrompt()
    ->addSystemMessage('You are a helpful assistant.')
    ->addUserMessage('{{question}}', parseTemplate: true);

$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => createParser('string'),
    'model' => 'gpt-4o-mini',
]);

// Add event listeners
$executor
    ->on(BeforeProviderCall::class, function (BeforeProviderCall $event): void {

        echo "[Hook] Making API request to model: {$event->request->model}\n";
    })
    ->on(AfterProviderCall::class, function (AfterProviderCall $event): void {
        $tokens = $event->response->usage?->getTotal() ?? 0;
        echo "[Hook] Received response, tokens used: {$tokens}\n";
    })
    ->on(OnSuccess::class, function (OnSuccess $event): void {
        echo "[Hook] Execution succeeded in {$event->durationMs}ms\n";
    })
    ->on(OnError::class, function (OnError $event): void {
        echo "[Hook] Error: {$event->error->getMessage()}\n";
    })
    ->on(OnComplete::class, function (OnComplete $event): void {
        $status = $event->success ? 'succeeded' : 'failed';
        echo "[Hook] Execution {$status} in {$event->durationMs}ms\n";
    });

// Execute
$result = $executor->execute([
    'question' => 'What is 2 + 2?',
]);

echo "\nAnswer: ".$result->getValue()."\n";
