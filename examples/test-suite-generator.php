<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createLlmExecutor;
use function HelgeSverre\Synapse\createParser;
use function HelgeSverre\Synapse\useLlm;

/**
 * Plans test cases for a given function without writing them.
 *
 * @return array<string> List of test case descriptions
 */
function planTestCases(string $functionCode, int $maxCases = 5): array
{
    $llm = useLlm('openai.gpt-4o-mini', [
        'apiKey' => getenv('OPENAI_API_KEY'),
    ]);

    $prompt = createChatPrompt()
        ->addSystemMessage('You are a senior PHP developer who writes comprehensive PHPUnit tests.')
        ->addUserMessage(<<<PROMPT
Analyze the following PHP function and list up to {$maxCases} test cases that should be written.

Only list the test cases, do not write the actual tests yet.
Return each test case description on its own line, starting with a dash (-).

Function to test:
```php
{$functionCode}
```
PROMPT);

    $executor = createLlmExecutor([
        'llm' => $llm,
        'prompt' => $prompt,
        'parser' => createParser('list'),
        'model' => 'gpt-4o-mini',
    ]);

    return $executor->execute()->getValue();
}

/**
 * Writes a single PHPUnit test case based on a requirement.
 */
function writeTestCase(string $functionCode, string $testRequirement): ?string
{
    $llm = useLlm('openai.gpt-4o-mini', [
        'apiKey' => getenv('OPENAI_API_KEY'),
    ]);

    $prompt = createChatPrompt()
        ->addSystemMessage('You are a senior PHP developer who writes clean, comprehensive PHPUnit tests.')
        ->addUserMessage(<<<PROMPT
Write a single PHPUnit test method for the following requirement.

Function to test:
```php
{$functionCode}
```

Test requirement: {$testRequirement}

Return only the test method (not the full class), wrapped in a PHP code block.
Use descriptive method names and include assertions.
PROMPT);

    $executor = createLlmExecutor([
        'llm' => $llm,
        'prompt' => $prompt,
        'parser' => createParser('codeblock', ['language' => 'php']),
        'model' => 'gpt-4o-mini',
    ]);

    $result = $executor->execute()->getValue();

    return $result['code'] ?? null;
}

/**
 * Generates a complete test suite by planning and then writing each test.
 *
 * @return array{requirements: array<string>, tests: array<string>}
 */
function generateTestSuite(string $functionCode, int $maxCases = 5): array
{
    echo "Planning test cases...\n\n";

    $requirements = planTestCases($functionCode, $maxCases);

    echo "Planned test cases:\n";
    foreach ($requirements as $index => $requirement) {
        echo sprintf("  %d. %s\n", $index + 1, $requirement);
    }
    echo "\n";

    $tests = [];

    foreach ($requirements as $index => $requirement) {
        echo sprintf("Writing test %d of %d: %s\n", $index + 1, count($requirements), $requirement);
        $test = writeTestCase($functionCode, $requirement);
        if ($test !== null) {
            $tests[] = $test;
        }
    }

    return [
        'requirements' => $requirements,
        'tests' => $tests,
    ];
}

$functionCode = <<<'PHP'
function divide(int $a, int $b): float {
    if ($b === 0) {
        throw new InvalidArgumentException('Cannot divide by zero');
    }
    return $a / $b;
}
PHP;

echo "=== Test Suite Generator Example ===\n\n";
echo "Function to test:\n";
echo $functionCode."\n\n";
echo str_repeat('=', 50)."\n\n";

$result = generateTestSuite($functionCode, 5);

echo "\n".str_repeat('=', 50)."\n";
echo "Generated Test Suite:\n";
echo str_repeat('=', 50)."\n\n";

echo "<?php\n\n";
echo "use PHPUnit\\Framework\\TestCase;\n\n";
echo "class DivideTest extends TestCase\n";
echo "{\n";

foreach ($result['tests'] as $test) {
    $lines = explode("\n", trim($test));
    foreach ($lines as $line) {
        echo '    '.$line."\n";
    }
    echo "\n";
}

echo "}\n";
