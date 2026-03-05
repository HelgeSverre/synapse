<?php

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createExecutor;
use function HelgeSverre\Synapse\createParser;

use HelgeSverre\Synapse\Options\ExecutorOptions;

use function HelgeSverre\Synapse\useLlm;

$providers = [
    useLlm('openai', ['apiKey' => getenv('OPENAI_API_KEY'), 'model' => 'gpt-4o-mini']),
    useLlm('anthropic', ['apiKey' => getenv('ANTHROPIC_API_KEY'), 'model' => 'claude-3-5-haiku-latest']),
];

$prompt = createChatPrompt()
    ->addSystemMessage('You are a concise support assistant.')
    ->addUserMessage('{{question}}', parseTemplate: true);

$lastError = null;

foreach ($providers as $llm) {
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        try {
            $executor = createExecutor(new ExecutorOptions(
                llm: $llm,
                prompt: $prompt,
                parser: createParser('string'),
                temperature: 0.1,
                maxTokens: 300,
            ));

            $result = $executor->run(['question' => 'What is the difference between retries and fallbacks?']);
            echo "Provider {$llm->getName()} succeeded on attempt {$attempt}:\n";
            echo $result->getValue()."\n";
            exit(0);
        } catch (Throwable $e) {
            $lastError = $e;
            usleep($attempt * 150_000); // simple linear backoff
        }
    }
}

throw new RuntimeException('All providers failed', previous: $lastError);
