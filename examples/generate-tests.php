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

// Sample PHP function to generate tests for
$sourceCode = <<<'PHP'
function add(int $a, int $b): int {
    return $a + $b;
}
PHP;

// Create a prompt asking the LLM to write PHPUnit tests
$prompt = createChatPrompt()
    ->addSystemMessage(
        'You are an expert PHP developer specializing in writing comprehensive PHPUnit tests. '.
        'When given source code, you write tests that achieve 100% code coverage. '.
        'Always respond with only the test code in a PHP code block.',
    )
    ->addUserMessage(
        "Write PHPUnit tests for the following PHP code. Ensure 100% code coverage.\n\n".
        "```php\n{{{code}}}\n```",
        parseTemplate: true,
    );

// Create a parser to extract the code block
$parser = createParser('codeblock', [
    'language' => 'php',
]);

// Create the executor
$executor = createLlmExecutor([
    'llm' => $llm,
    'prompt' => $prompt,
    'parser' => $parser,
    'model' => 'gpt-4o-mini',
]);

// Execute with the source code as input
$result = $executor->execute([
    'code' => $sourceCode,
]);

echo "Generated PHPUnit Tests:\n";
echo "========================\n\n";
echo $result->getValue()."\n";
