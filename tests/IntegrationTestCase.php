<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Base test case for integration tests.
 *
 * Automatically handles #[RequiresEnv] attributes on classes and methods,
 * skipping tests when required environment variables are not set.
 *
 * Usage:
 *   #[RequiresEnv('OPENAI_API_KEY')]
 *   final class OpenAIIntegrationTest extends IntegrationTestCase
 *   {
 *       public function test_something(): void { ... }
 *   }
 *
 * Or per-method:
 *   final class MixedTest extends IntegrationTestCase
 *   {
 *       #[RequiresEnv('OPENAI_API_KEY')]
 *       public function test_openai(): void { ... }
 *
 *       #[RequiresEnv('ANTHROPIC_API_KEY')]
 *       public function test_anthropic(): void { ... }
 *   }
 */
abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->checkRequiredEnvVars();
    }

    private function checkRequiredEnvVars(): void
    {
        // Check class-level attributes
        $classReflection = new ReflectionClass($this);
        foreach ($classReflection->getAttributes(RequiresEnv::class) as $attribute) {
            /** @var RequiresEnv $requiresEnv */
            $requiresEnv = $attribute->newInstance();
            [$satisfied, $missing] = $requiresEnv->check();
            if (! $satisfied) {
                $this->markTestSkipped("{$missing} not set");
            }
        }

        // Check method-level attributes
        $methodName = $this->name();
        if ($methodName !== '' && method_exists($this, $methodName)) {
            $methodReflection = new ReflectionMethod($this, $methodName);
            foreach ($methodReflection->getAttributes(RequiresEnv::class) as $attribute) {
                /** @var RequiresEnv $requiresEnv */
                $requiresEnv = $attribute->newInstance();
                [$satisfied, $missing] = $requiresEnv->check();
                if (! $satisfied) {
                    $this->markTestSkipped("{$missing} not set");
                }
            }
        }
    }

    /**
     * Helper to get an environment variable or skip the test.
     *
     * @deprecated Use #[RequiresEnv('VAR_NAME')] attribute instead
     */
    protected function requireEnv(string $envVar): string
    {
        $value = getenv($envVar);
        if ($value === false || $value === '') {
            $this->markTestSkipped("{$envVar} not set");
        }

        return $value;
    }
}
