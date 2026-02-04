<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use function HelgeSverre\Synapse\createCoreExecutor;

// Create a simple executor from a function
$calculator = createCoreExecutor(function (array $input): int {
    $a = $input['a'] ?? 0;
    $b = $input['b'] ?? 0;
    $operation = $input['operation'] ?? 'add';

    return match ($operation) {
        'add' => $a + $b,
        'subtract' => $a - $b,
        'multiply' => $a * $b,
        'divide' => $b !== 0 ? intdiv($a, $b) : 0,
        default => throw new InvalidArgumentException("Unknown operation: {$operation}"),
    };
}, 'calculator');

// Execute
$result = $calculator->execute([
    'a' => 10,
    'b' => 5,
    'operation' => 'multiply',
]);

echo 'Result: '.$result->getValue()."\n"; // Output: 50

// Chain executors
$formatter = createCoreExecutor(function (array $input): string {
    return "The result is: {$input['value']}";
}, 'formatter');

$calcResult = $calculator->execute(['a' => 15, 'b' => 3, 'operation' => 'add']);
$formatted = $formatter->execute(['value' => $calcResult->getValue()]);

echo $formatted->getValue()."\n"; // Output: The result is: 18
