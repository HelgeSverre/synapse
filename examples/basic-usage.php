<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use function LlmExe\createChatPrompt;
use function LlmExe\createLlmExecutor;
use function LlmExe\createParser;

use LlmExe\Factory;

use function LlmExe\useLlm;

// Configure transport (using Guzzle as example)
$client = new \GuzzleHttp\Client;
$psr17Factory = new \GuzzleHttp\Psr7\HttpFactory;
$transport = Factory::createTransport($client, $psr17Factory, $psr17Factory);
Factory::setDefaultTransport($transport);

// Create an LLM provider
$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);

// Create a prompt
$prompt = createChatPrompt()
    ->addSystemMessage('You are a helpful assistant that answers questions concisely.')
    ->addUserMessage('{{question}}');

// Create a parser
$parser = createParser('string');

// Create the executor
$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => $parser,
    'model' => 'gpt-4o-mini',
]);

// Execute with input
$result = $executor->execute([
    'question' => 'What is the capital of France?',
]);

echo 'Answer: '.$result->getValue()."\n";
echo 'Tokens used: '.$result->response->usage?->getTotal()."\n";
