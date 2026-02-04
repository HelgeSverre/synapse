<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests;

use Attribute;

/**
 * Marks a test as requiring one or more environment variables.
 *
 * Usage:
 *   #[RequiresEnv('OPENAI_API_KEY')]
 *   public function test_something(): void { ... }
 *
 *   #[RequiresEnv('OPENAI_API_KEY', 'ANTHROPIC_API_KEY')]
 *   public function test_both(): void { ... }
 *
 * The test will be skipped if any of the specified environment variables
 * are not set or are empty.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class RequiresEnv
{
    /** @var list<string> */
    public array $envVars;

    public function __construct(string ...$envVars)
    {
        $this->envVars = $envVars;
    }

    /**
     * Check if all required environment variables are set.
     *
     * @return array{0: bool, 1: string} [satisfied, missing var name]
     */
    public function check(): array
    {
        foreach ($this->envVars as $envVar) {
            $value = getenv($envVar);
            if ($value === false || $value === '') {
                return [false, $envVar];
            }
        }

        return [true, ''];
    }
}
