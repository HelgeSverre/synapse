<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit;

use function HelgeSverre\Synapse\createPrompt;
use function HelgeSverre\Synapse\createToolRegistry;

use HelgeSverre\Synapse\Executor\ToolRegistry;
use HelgeSverre\Synapse\Prompt\ChatPrompt;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use PHPUnit\Framework\TestCase;

final class FunctionsTest extends TestCase
{
    public function test_create_prompt_creates_chat_prompt_by_default(): void
    {
        $prompt = createPrompt();

        $this->assertInstanceOf(ChatPrompt::class, $prompt);
    }

    public function test_create_prompt_creates_text_prompt_when_requested(): void
    {
        $prompt = createPrompt('text');

        $this->assertInstanceOf(TextPrompt::class, $prompt);
    }

    public function test_create_prompt_throws_for_invalid_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown prompt type: invalid. Expected 'chat' or 'text'.");

        createPrompt('invalid');
    }

    public function test_create_tool_registry_returns_tool_registry(): void
    {
        $registry = createToolRegistry([
            [
                'name' => 'echo_tool',
                'description' => 'Echo input',
                'handler' => fn (array $input): array => $input,
            ],
        ]);

        $this->assertInstanceOf(ToolRegistry::class, $registry);
        $this->assertTrue($registry->hasFunction('echo_tool'));
    }

    public function test_create_tool_registry_returns_registry_for_simple_config(): void
    {
        $registry = createToolRegistry([
            [
                'name' => 'noop',
                'description' => 'No-op',
                'handler' => fn (): string => 'ok',
            ],
        ]);

        $this->assertInstanceOf(ToolRegistry::class, $registry);
        $this->assertTrue($registry->hasFunction('noop'));
    }
}
