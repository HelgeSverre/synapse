<?php

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createExecutor;
use function HelgeSverre\Synapse\createParser;

use HelgeSverre\Synapse\Hooks\Events\AfterProviderCall;
use HelgeSverre\Synapse\Hooks\Events\OnError;
use HelgeSverre\Synapse\Hooks\Events\OnSuccess;
use HelgeSverre\Synapse\Options\ExecutorOptions;

use function HelgeSverre\Synapse\useLlm;

$llm = useLlm('openai', ['apiKey' => getenv('OPENAI_API_KEY'), 'model' => 'gpt-4o-mini']);

$executor = createExecutor(new ExecutorOptions(
    llm: $llm,
    prompt: createChatPrompt()
        ->addSystemMessage('You are concise.')
        ->addUserMessage('{{question}}', parseTemplate: true),
    parser: createParser('string'),
));

$executor
    ->on(AfterProviderCall::class, function (AfterProviderCall $event): void {
        $tokens = $event->response->usage?->getTotal() ?? 0;
        error_log(json_encode([
            'event' => 'after_provider_call',
            'model' => $event->request->model,
            'tokens' => $tokens,
        ], JSON_UNESCAPED_SLASHES));
    })
    ->on(OnSuccess::class, fn (OnSuccess $event): bool => error_log("run_success duration_ms={$event->durationMs}") || true)
    ->on(OnError::class, fn (OnError $event): bool => error_log("run_error error={$event->error->getMessage()}") || true);

$result = $executor->run(['question' => 'Explain idempotency in one sentence']);
echo $result->getValue()."\n";
