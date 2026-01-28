<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Executor;

use LlmExe\Executor\CallableExecutor;
use LlmExe\Executor\UseExecutors;
use LlmExe\Provider\Request\ToolDefinition;
use LlmExe\State\ConversationState;
use PHPUnit\Framework\TestCase;

final class UseExecutorsTest extends TestCase
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
        $useExecutors = new UseExecutors([]);

        $this->assertSame([], $useExecutors->getFunctions());
    }

    public function test_constructor_with_array_of_executors(): void
    {
        $executor1 = $this->createExecutor('func1');
        $executor2 = $this->createExecutor('func2');

        $useExecutors = new UseExecutors([$executor1, $executor2]);

        $this->assertCount(2, $useExecutors->getFunctions());
        $this->assertTrue($useExecutors->hasFunction('func1'));
        $this->assertTrue($useExecutors->hasFunction('func2'));
    }

    public function test_register_adds_executor(): void
    {
        $useExecutors = new UseExecutors;
        $executor = $this->createExecutor('test');

        $result = $useExecutors->register($executor);

        $this->assertSame($useExecutors, $result);
        $this->assertTrue($useExecutors->hasFunction('test'));
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

        $useExecutors = new UseExecutors([$executor1]);
        $useExecutors->register($executor2);

        $this->assertCount(1, $useExecutors->getFunctions());
        $this->assertSame('Second', $useExecutors->getFunction('duplicate')?->getDescription());
    }

    public function test_has_function_returns_true_for_existing(): void
    {
        $useExecutors = new UseExecutors([$this->createExecutor('exists')]);

        $this->assertTrue($useExecutors->hasFunction('exists'));
    }

    public function test_has_function_returns_false_for_non_existing(): void
    {
        $useExecutors = new UseExecutors;

        $this->assertFalse($useExecutors->hasFunction('nonexistent'));
    }

    public function test_get_function_returns_executor_by_name(): void
    {
        $executor = $this->createExecutor('myFunc');
        $useExecutors = new UseExecutors([$executor]);

        $result = $useExecutors->getFunction('myFunc');

        $this->assertSame($executor, $result);
    }

    public function test_get_function_returns_null_for_non_existing(): void
    {
        $useExecutors = new UseExecutors;

        $result = $useExecutors->getFunction('nonexistent');

        $this->assertNull($result);
    }

    public function test_get_functions_returns_all_executors(): void
    {
        $executor1 = $this->createExecutor('func1');
        $executor2 = $this->createExecutor('func2');
        $useExecutors = new UseExecutors([$executor1, $executor2]);

        $result = $useExecutors->getFunctions();

        $this->assertCount(2, $result);
        $this->assertContains($executor1, $result);
        $this->assertContains($executor2, $result);
    }

    public function test_get_functions_returns_list_not_associative(): void
    {
        $executor1 = $this->createExecutor('a');
        $executor2 = $this->createExecutor('b');
        $useExecutors = new UseExecutors([$executor1, $executor2]);

        $result = $useExecutors->getFunctions();

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

        $useExecutors = new UseExecutors([$executor1, $executor2]);

        $definitions = $useExecutors->getToolDefinitions();

        $this->assertCount(2, $definitions);
        $this->assertContainsOnlyInstancesOf(ToolDefinition::class, $definitions);
        $this->assertSame('add', $definitions[0]->name);
        $this->assertSame('greet', $definitions[1]->name);
    }

    public function test_get_tool_definitions_returns_empty_array_when_no_executors(): void
    {
        $useExecutors = new UseExecutors;

        $definitions = $useExecutors->getToolDefinitions();

        $this->assertSame([], $definitions);
    }

    public function test_call_function_executes_and_returns_result(): void
    {
        $executor = new CallableExecutor(
            name: 'multiply',
            description: 'Multiply',
            handler: fn ($input): int|float => $input['a'] * $input['b'],
        );
        $useExecutors = new UseExecutors([$executor]);

        $result = $useExecutors->callFunction('multiply', ['a' => 3, 'b' => 4]);

        $this->assertSame(12, $result);
    }

    public function test_call_function_throws_for_unknown_function(): void
    {
        $useExecutors = new UseExecutors;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown function: unknown');

        $useExecutors->callFunction('unknown', []);
    }

    public function test_call_function_returns_error_json_on_failure(): void
    {
        $executor = new CallableExecutor(
            name: 'failing',
            description: 'Fails',
            handler: fn () => throw new \RuntimeException('Something went wrong'),
        );
        $useExecutors = new UseExecutors([$executor]);

        $result = $useExecutors->callFunction('failing', []);

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
        $useExecutors = new UseExecutors([$executor]);

        $result = $useExecutors->validateFunctionInput('test', []);

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['errors']);
    }

    public function test_validate_function_input_returns_invalid_for_unknown_function(): void
    {
        $useExecutors = new UseExecutors;

        $result = $useExecutors->validateFunctionInput('unknown', []);

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
        $useExecutors = new UseExecutors([$executor]);

        $validResult = $useExecutors->validateFunctionInput('validated', ['required' => 'value']);
        $invalidResult = $useExecutors->validateFunctionInput('validated', []);

        $this->assertTrue($validResult['valid']);
        $this->assertFalse($invalidResult['valid']);
        $this->assertContains('required field missing', $invalidResult['errors']);
    }

    public function test_get_visible_functions_returns_all_when_no_visibility_handler(): void
    {
        $executor1 = $this->createExecutor('func1');
        $executor2 = $this->createExecutor('func2');
        $useExecutors = new UseExecutors([$executor1, $executor2]);

        $visible = $useExecutors->getVisibleFunctions();

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
        $useExecutors = new UseExecutors([$visibleExecutor, $hiddenExecutor]);

        $visible = $useExecutors->getVisibleFunctions();

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
        $useExecutors = new UseExecutors([$executor]);
        $state = new ConversationState;

        $useExecutors->getVisibleFunctions(['key' => 'value'], $state);

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
        $useExecutors = new UseExecutors([$visibleExecutor, $hiddenExecutor]);

        $definitions = $useExecutors->getVisibleToolDefinitions();

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
        $useExecutors = new UseExecutors([$executor]);
        $state = new ConversationState;

        $useExecutors->callFunction('stateful', [], $state);

        $this->assertSame($state, $receivedState);
    }
}
