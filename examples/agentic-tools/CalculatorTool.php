<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\AgenticTools;

use HelgeSverre\Synapse\Executor\CallableExecutor;

final class CalculatorTool
{
    public static function create(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'calculator',
            description: 'Perform mathematical calculations. Supports +, -, *, /, ^ (power), sqrt(), and parentheses.',
            handler: function (array $args): string {
                $expression = $args['expression'] ?? '';

                $sanitized = preg_replace('/[^0-9+\-*\/().^sqrt\s]/', '', $expression);

                if ($sanitized === '' || $sanitized === null) {
                    return json_encode(['error' => 'Invalid expression'], JSON_THROW_ON_ERROR);
                }

                /** @var string $sanitized */
                $sanitized = str_replace('^', '**', $sanitized);
                $replaced = preg_replace('/sqrt\s*\(/', 'sqrt(', $sanitized);
                /** @var string $evalExpr */
                $evalExpr = is_string($replaced) ? $replaced : $sanitized;

                try {
                    $result = @eval("return {$evalExpr};");

                    if ($result === false || $result === null) {
                        return json_encode(['error' => 'Could not evaluate expression'], JSON_THROW_ON_ERROR);
                    }

                    if (is_float($result) && floor($result) == $result) {
                        $result = (int) $result;
                    } elseif (is_float($result)) {
                        $result = round($result, 10);
                        $formatted = rtrim(rtrim(sprintf('%.10f', $result), '0'), '.');
                        $result = str_contains($formatted, '.') ? (float) $formatted : (int) $formatted;
                    }

                    return json_encode([
                        'expression' => $expression,
                        'result' => $result,
                    ], JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    return json_encode(['error' => 'Failed to evaluate expression'], JSON_THROW_ON_ERROR);
                }
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'expression' => [
                        'type' => 'string',
                        'description' => 'Mathematical expression to evaluate, e.g. "2 + 2", "sqrt(16)", "3^2"',
                    ],
                ],
                'required' => ['expression'],
            ],
        );
    }
}
