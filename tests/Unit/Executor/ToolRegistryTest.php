<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Executor;

use HelgeSverre\Synapse\Executor\CallableExecutor;
use HelgeSverre\Synapse\Executor\ToolRegistry;
use HelgeSverre\Synapse\State\ConversationState;
use PHPUnit\Framework\TestCase;

final class ToolRegistryTest extends TestCase
{
    private function createExecutor(string $name, string $description = 'Test executor'): CallableExecutor
    {
        return new CallableExecutor(
            name: $name,
            description: $description,
            handler: fn (): string => 'result',
        );
    }

    public function test_constructor_with_empty_array(): void
    {
        $registry = new ToolRegistry([]);

        $this->assertSame([], $registry->getFunctions());
    }

    public function test_constructor_with_array_of_executors(): void
    {
        $executor1 = $this->createExecutor('func1');
        $executor2 = $this->createExecutor('func2');

        $registry = new ToolRegistry([$executor1, $executor2]);

        $this->assertCount(2, $registry->getFunctions());
        $this->assertTrue($registry->hasFunction('func1'));
        $this->assertTrue($registry->hasFunction('func2'));
    }

    public function test_register_adds_executor(): void
    {
        $registry = new ToolRegistry;
        $executor = $this->createExecutor('test');

        $result = $registry->register($executor);

        $this->assertSame($registry, $result);
        $this->assertTrue($registry->hasFunction('test'));
    }

    public function test_register_overwrites_executor_with_same_name(): void
    {
        $executor1 = new CallableExecutor(
            name: 'duplicate',
            description: 'First',
            handler: fn (): string => 'first',
        );
        $executor2 = new CallableExecutor(
            name: 'duplicate',
            description: 'Second',
            handler: fn (): string => 'second',
        );

        $registry = new ToolRegistry([$executor1]);
        $registry->register($executor2);

        $this->assertCount(1, $registry->getFunctions());
        $this->assertSame('Second', $registry->getFunction('duplicate')?->getDescription());
    }

    public function test_has_function_returns_true_for_existing(): void
    {
        $registry = new ToolRegistry([$this->createExecutor('exists')]);

        $this->assertTrue($registry->hasFunction('exists'));
    }

    public function test_has_function_returns_false_for_non_existing(): void
    {
        $registry = new ToolRegistry;

        $this->assertFalse($registry->hasFunction('nonexistent'));
    }

    public function test_get_function_returns_executor_by_name(): void
    {
        $executor = $this->createExecutor('myFunc');
        $registry = new ToolRegistry([$executor]);

        $result = $registry->getFunction('myFunc');

        $this->assertSame($executor, $result);
    }

    public function test_get_function_returns_null_for_non_existing(): void
    {
        $registry = new ToolRegistry;

        $result = $registry->getFunction('nonexistent');

        $this->assertNull($result);
    }

    public function test_get_functions_returns_all_executors(): void
    {
        $executor1 = $this->createExecutor('func1');
        $executor2 = $this->createExecutor('func2');
        $registry = new ToolRegistry([$executor1, $executor2]);

        $result = $registry->getFunctions();

        $this->assertCount(2, $result);
        $this->assertContains($executor1, $result);
        $this->assertContains($executor2, $result);
    }

    public function test_get_functions_returns_list_not_associative(): void
    {
        $executor1 = $this->createExecutor('a');
        $executor2 = $this->createExecutor('b');
        $registry = new ToolRegistry([$executor1, $executor2]);

        $result = $registry->getFunctions();

        $this->assertSame([0, 1], array_keys($result));
    }

    public function test_get_tool_definitions_returns_array_of_tool_definitions(): void
    {
        $executor1 = new CallableExecutor(
            name: 'add',
            description: 'Add numbers',
            handler: fn ($input): float|int|array => $input['a'] + $input['b'],
            parameters: ['type' => 'object', 'properties' => ['a' => ['type' => 'number'], 'b' => ['type' => 'number']]],
        );
        $executor2 = new CallableExecutor(
            name: 'greet',
            description: 'Greet someone',
            handler: fn ($input): string => "Hello, {$input['name']}!",
        );

        $registry = new ToolRegistry([$executor1, $executor2]);

        $definitions = $registry->getToolDefinitions();

        $this->assertCount(2, $definitions);
        $this->assertSame('add', $definitions[0]->name);
        $this->assertSame('greet', $definitions[1]->name);
    }

    public function test_get_tool_definitions_returns_empty_array_when_no_executors(): void
    {
        $registry = new ToolRegistry;

        $definitions = $registry->getToolDefinitions();

        $this->assertSame([], $definitions);
    }

    public function test_call_function_executes_and_returns_result(): void
    {
        $executor = new CallableExecutor(
            name: 'multiply',
            description: 'Multiply',
            handler: fn ($input): int|float => $input['a'] * $input['b'],
        );
        $registry = new ToolRegistry([$executor]);

        $result = $registry->callFunction('multiply', ['a' => 3, 'b' => 4]);

        $this->assertSame(12, $result);
    }

    public function test_call_function_result_returns_structured_success(): void
    {
        $executor = new CallableExecutor(
            name: 'multiply',
            description: 'Multiply',
            handler: fn ($input): int|float => $input['a'] * $input['b'],
        );
        $registry = new ToolRegistry([$executor]);

        $result = $registry->callFunctionResult('multiply', ['a' => 2, 'b' => 5]);

        $this->assertTrue($result->success);
        $this->assertSame(10, $result->result);
        $this->assertSame([], $result->errors);
    }

    public function test_call_function_throws_for_unknown_function(): void
    {
        $registry = new ToolRegistry;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown function: unknown');

        $registry->callFunction('unknown', []);
    }

    public function test_call_function_result_returns_failure_for_unknown_function(): void
    {
        $registry = new ToolRegistry;

        $result = $registry->callFunctionResult('unknown', []);

        $this->assertFalse($result->success);
        $this->assertContains('Unknown function: unknown', $result->errors);
    }

    public function test_call_function_returns_error_json_on_failure(): void
    {
        $executor = new CallableExecutor(
            name: 'failing',
            description: 'Fails',
            handler: fn () => throw new \RuntimeException('Something went wrong'),
        );
        $registry = new ToolRegistry([$executor]);

        $result = $registry->callFunction('failing', []);

        $this->assertIsString($result);
        $decoded = json_decode($result, true);
        $this->assertArrayHasKey('error', $decoded);
        $this->assertStringContainsString('Something went wrong', $decoded['error']);
    }

    public function test_validate_function_input_returns_valid_for_known_function(): void
    {
        $executor = new CallableExecutor(
            name: 'test',
            description: 'Test',
            handler: fn (): string => 'ok',
        );
        $registry = new ToolRegistry([$executor]);

        $result = $registry->validateFunctionInput('test', []);

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['errors']);
    }

    public function test_validate_function_input_returns_invalid_for_unknown_function(): void
    {
        $registry = new ToolRegistry;

        $result = $registry->validateFunctionInput('unknown', []);

        $this->assertFalse($result['valid']);
        $this->assertContains('Unknown function: unknown', $result['errors']);
    }

    public function test_validate_function_input_uses_executor_validation(): void
    {
        $executor = new CallableExecutor(
            name: 'validated',
            description: 'Validated',
            handler: fn (): string => 'ok',
            validateInputHandler: fn ($input): array => isset($input['required'])
                ? ['valid' => true, 'errors' => []]
                : ['valid' => false, 'errors' => ['required field missing']],
        );
        $registry = new ToolRegistry([$executor]);

        $validResult = $registry->validateFunctionInput('validated', ['required' => 'value']);
        $invalidResult = $registry->validateFunctionInput('validated', []);

        $this->assertTrue($validResult['valid']);
        $this->assertFalse($invalidResult['valid']);
        $this->assertContains('required field missing', $invalidResult['errors']);
    }

    public function test_get_visible_functions_returns_all_when_no_visibility_handler(): void
    {
        $executor1 = $this->createExecutor('func1');
        $executor2 = $this->createExecutor('func2');
        $registry = new ToolRegistry([$executor1, $executor2]);

        $visible = $registry->getVisibleFunctions();

        $this->assertCount(2, $visible);
    }

    public function test_get_visible_functions_filters_by_visibility_handler(): void
    {
        $visibleExecutor = new CallableExecutor(
            name: 'visible',
            description: 'Visible',
            handler: fn (): string => 'ok',
            visibilityHandler: fn (): true => true,
        );
        $hiddenExecutor = new CallableExecutor(
            name: 'hidden',
            description: 'Hidden',
            handler: fn (): string => 'ok',
            visibilityHandler: fn (): false => false,
        );
        $registry = new ToolRegistry([$visibleExecutor, $hiddenExecutor]);

        $visible = $registry->getVisibleFunctions();

        $this->assertCount(1, $visible);
        $this->assertSame('visible', $visible[0]->getName());
    }

    public function test_get_visible_functions_passes_input_and_state(): void
    {
        $receivedInput = null;
        $receivedState = null;
        $executor = new CallableExecutor(
            name: 'test',
            description: 'Test',
            handler: fn (): string => 'ok',
            visibilityHandler: function ($input, $state) use (&$receivedInput, &$receivedState): true {
                $receivedInput = $input;
                $receivedState = $state;

                return true;
            },
        );
        $registry = new ToolRegistry([$executor]);
        $state = new ConversationState;

        $registry->getVisibleFunctions(['key' => 'value'], $state);

        $this->assertSame(['key' => 'value'], $receivedInput);
        $this->assertSame($state, $receivedState);
    }

    public function test_get_visible_tool_definitions_returns_filtered_definitions(): void
    {
        $visibleExecutor = new CallableExecutor(
            name: 'visible',
            description: 'Visible',
            handler: fn (): string => 'ok',
            visibilityHandler: fn (): true => true,
        );
        $hiddenExecutor = new CallableExecutor(
            name: 'hidden',
            description: 'Hidden',
            handler: fn (): string => 'ok',
            visibilityHandler: fn (): false => false,
        );
        $registry = new ToolRegistry([$visibleExecutor, $hiddenExecutor]);

        $definitions = $registry->getVisibleToolDefinitions();

        $this->assertCount(1, $definitions);
        $this->assertSame('visible', $definitions[0]->name);
    }

    public function test_call_function_with_state(): void
    {
        $receivedState = null;
        $executor = new CallableExecutor(
            name: 'stateful',
            description: 'Uses state',
            handler: function ($input, $state) use (&$receivedState): string {
                $receivedState = $state;

                return 'ok';
            },
        );
        $registry = new ToolRegistry([$executor]);
        $state = new ConversationState;

        $registry->callFunction('stateful', [], $state);

        $this->assertSame($state, $receivedState);
    }
}
