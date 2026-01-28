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

// Create a prompt that asks the LLM to write code based on a spec
$prompt = createChatPrompt()
    ->addSystemMessage("You are a senior PHP developer. Write a concise implementation for the task: '{{spec}}'. Respond with only the code (no questions or explanation), inside a single php code block");

// Create a parser to extract code from markdown code blocks
$parser = createParser('markdownCodeBlock', [
    'language' => 'php',
]);

// Create the executor
$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => $parser,
    'model' => 'gpt-4o-mini',
    'parseTemplate' => true,
]);

// Execute with sample spec
$result = $executor->execute([
    'spec' => 'a function that calculates the factorial of a number',
]);

echo "Generated Code:\n";
echo "---------------\n";
echo $result->getValue()."\n";
