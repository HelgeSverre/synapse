<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Executor;

use HelgeSverre\Synapse\Executor\CoreExecutor;
use HelgeSverre\Synapse\Hooks\HookDispatcher;
use HelgeSverre\Synapse\State\ConversationState;
use PHPUnit\Framework\TestCase;

final class CoreExecutorTest extends TestCase
{
    public function test_executes_handler_and_returns_result(): void
    {
        $executor = new CoreExecutor(fn (array $input): int|float => $input['value'] * 2);

        $result = $executor->execute(['value' => 5]);

        $this->assertSame(10, $result->getValue());
    }

    public function test_handler_receives_input_correctly(): void
    {
        $receivedInput = null;
        $executor = new CoreExecutor(function (array $input) use (&$receivedInput): string {
            $receivedInput = $input;

            return 'done';
        });

        $executor->execute(['foo' => 'bar', 'baz' => 123]);

        $this->assertSame(['foo' => 'bar', 'baz' => 123], $receivedInput);
    }

    public function test_result_contains_string_value(): void
    {
        $executor = new CoreExecutor(fn (): string => 'hello world');

        $result = $executor->execute([]);

        $this->assertSame('hello world', $result->getValue());
        $this->assertSame('hello world', $result->response->text);
    }

    public function test_result_contains_array_value_as_json(): void
    {
        $executor = new CoreExecutor(fn (): array => ['key' => 'value']);

        $result = $executor->execute([]);

        $this->assertSame(['key' => 'value'], $result->getValue());
        $this->assertSame('{"key":"value"}', $result->response->text);
    }

    public function test_metadata_is_populated_correctly(): void
    {
        $executor = new CoreExecutor(fn (): string => 'test');

        $executor->execute([]);

        $metadata = $executor->getMetadata();
        $this->assertSame('CoreExecutor', $metadata->name);
        $this->assertSame(CoreExecutor::class, $metadata->type);
        $this->assertSame(1, $metadata->executions);
    }

    public function test_execution_count_increments(): void
    {
        $executor = new CoreExecutor(fn (): string => 'test');

        $executor->execute([]);
        $executor->execute([]);
        $executor->execute([]);

        $this->assertSame(3, $executor->getMetadata()->executions);
    }

    public function test_named_executor(): void
    {
        $executor = new CoreExecutor(fn (): string => 'test', name: 'MyCustomExecutor');

        $executor->execute([]);

        $this->assertSame('MyCustomExecutor', $executor->getMetadata()->name);
    }

    public function test_uses_custom_hooks(): void
    {
        $hooks = new HookDispatcher;
        $executor = new CoreExecutor(fn (): string => 'test', hooks: $hooks);

        $this->assertSame($hooks, $executor->getHooks());
    }

    public function test_uses_custom_state(): void
    {
        $state = new ConversationState;
        $executor = new CoreExecutor(fn (): string => 'test', state: $state);

        $this->assertSame($state, $executor->getState());
    }

    public function test_result_contains_state(): void
    {
        $state = new ConversationState;
        $executor = new CoreExecutor(fn (): string => 'test', state: $state);

        $result = $executor->execute([]);

        $this->assertSame($state, $result->state);
    }

    public function test_response_has_core_model(): void
    {
        $executor = new CoreExecutor(fn (): string => 'test');

        $result = $executor->execute([]);

        $this->assertSame('core', $result->response->model);
    }

    public function test_response_has_zero_usage(): void
    {
        $executor = new CoreExecutor(fn (): string => 'test');

        $result = $executor->execute([]);

        $this->assertNotNull($result->response->usage);
        $this->assertSame(0, $result->response->usage->inputTokens);
        $this->assertSame(0, $result->response->usage->outputTokens);
    }

    public function test_throws_exception_from_handler(): void
    {
        $executor = new CoreExecutor(fn () => throw new \RuntimeException('Handler failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Handler failed');

        $executor->execute([]);
    }

    public function test_exception_does_not_increment_successful_executions(): void
    {
        $executor = new CoreExecutor(fn () => throw new \RuntimeException('fail'));

        try {
            $executor->execute([]);
        } catch (\RuntimeException) {
        }

        $this->assertSame(1, $executor->getMetadata()->executions);
    }
}
