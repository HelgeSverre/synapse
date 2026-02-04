<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Executor;

use HelgeSverre\Synapse\Executor\CallableExecutor;
use HelgeSverre\Synapse\Executor\ToolResult;
use HelgeSverre\Synapse\Provider\Request\ToolDefinition;
use HelgeSverre\Synapse\State\ConversationState;
use PHPUnit\Framework\TestCase;

final class CallableExecutorTest extends TestCase
{
    public function test_constructor_sets_name_and_description(): void
    {
        $executor = new CallableExecutor(
            name: 'test_tool',
            description: 'A test tool',
            handler: fn (array $input): array => $input,
        );

        $this->assertSame('test_tool', $executor->getName());
        $this->assertSame('A test tool', $executor->getDescription());
    }

    public function test_constructor_sets_parameters(): void
    {
        $parameters = [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
            ],
            'required' => ['query'],
        ];

        $executor = new CallableExecutor(
            name: 'search',
            description: 'Search tool',
            handler: fn (array $input): array => $input,
            parameters: $parameters,
        );

        $this->assertSame($parameters, $executor->getParameters());
    }

    public function test_constructor_sets_attributes(): void
    {
        $attributes = ['category' => 'utility', 'priority' => 1];

        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: fn (array $input): array => $input,
            attributes: $attributes,
        );

        $this->assertSame($attributes, $executor->getAttributes());
    }

    public function test_execute_calls_handler_with_input(): void
    {
        $receivedInput = null;
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: function (array $input) use (&$receivedInput): string {
                $receivedInput = $input;

                return 'done';
            },
        );

        $executor->execute(['foo' => 'bar', 'num' => 42]);

        $this->assertSame(['foo' => 'bar', 'num' => 42], $receivedInput);
    }

    public function test_execute_calls_handler_with_state(): void
    {
        $receivedState = null;
        $state = new ConversationState;
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: function (array $input, ConversationState $state) use (&$receivedState): string {
                $receivedState = $state;

                return 'done';
            },
        );

        $executor->execute([], $state);

        $this->assertSame($state, $receivedState);
    }

    public function test_execute_creates_default_state_when_null(): void
    {
        $receivedState = null;
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: function (array $input, ConversationState $state) use (&$receivedState): string {
                $receivedState = $state;

                return 'done';
            },
        );

        $executor->execute([]);

        $this->assertInstanceOf(ConversationState::class, $receivedState);
    }

    public function test_execute_returns_tool_result_on_success(): void
    {
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: fn (array $input): string => 'result value',
        );

        $result = $executor->execute([]);

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('result value', $result->result);
        $this->assertSame([], $result->errors);
    }

    public function test_execute_returns_attributes_in_result(): void
    {
        $attributes = ['key' => 'value'];
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: fn (array $input): string => 'result',
            attributes: $attributes,
        );

        $result = $executor->execute([]);

        $this->assertSame($attributes, $result->attributes);
    }

    public function test_execute_catches_exception_and_returns_error_result(): void
    {
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: fn (array $input) => throw new \RuntimeException('Something went wrong'),
        );

        $result = $executor->execute([]);

        $this->assertFalse($result->success);
        $this->assertNull($result->result);
        $this->assertSame(['Something went wrong'], $result->errors);
    }

    public function test_is_visible_returns_true_when_no_visibility_handler(): void
    {
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: fn (array $input): string => 'result',
        );

        $this->assertTrue($executor->isVisible([]));
    }

    public function test_is_visible_calls_visibility_handler(): void
    {
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: fn (array $input): string => 'result',
            visibilityHandler: fn (array $input, ConversationState $state): mixed => $input['show'] ?? false,
        );

        $this->assertFalse($executor->isVisible([]));
        $this->assertFalse($executor->isVisible(['show' => false]));
        $this->assertTrue($executor->isVisible(['show' => true]));
    }

    public function test_is_visible_receives_state(): void
    {
        $receivedState = null;
        $state = new ConversationState;
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: fn (array $input): string => 'result',
            visibilityHandler: function (array $input, ConversationState $state) use (&$receivedState): true {
                $receivedState = $state;

                return true;
            },
        );

        $executor->isVisible([], $state);

        $this->assertSame($state, $receivedState);
    }

    public function test_is_visible_creates_default_state_when_null(): void
    {
        $receivedState = null;
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: fn (array $input): string => 'result',
            visibilityHandler: function (array $input, ConversationState $state) use (&$receivedState): true {
                $receivedState = $state;

                return true;
            },
        );

        $executor->isVisible([]);

        $this->assertInstanceOf(ConversationState::class, $receivedState);
    }

    public function test_validate_input_returns_valid_when_no_handler(): void
    {
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: fn (array $input): string => 'result',
        );

        $result = $executor->validateInput(['any' => 'input']);

        $this->assertSame(['valid' => true, 'errors' => []], $result);
    }

    public function test_validate_input_calls_handler(): void
    {
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: fn (array $input): string => 'result',
            validateInputHandler: function (array $input): array {
                if (! isset($input['required_field'])) {
                    return ['valid' => false, 'errors' => ['required_field is missing']];
                }

                return ['valid' => true, 'errors' => []];
            },
        );

        $invalid = $executor->validateInput([]);
        $valid = $executor->validateInput(['required_field' => 'value']);

        $this->assertFalse($invalid['valid']);
        $this->assertSame(['required_field is missing'], $invalid['errors']);
        $this->assertTrue($valid['valid']);
        $this->assertSame([], $valid['errors']);
    }

    public function test_execute_validates_input_before_calling_handler(): void
    {
        $handlerCalled = false;
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: function (array $input) use (&$handlerCalled): string {
                $handlerCalled = true;

                return 'result';
            },
            validateInputHandler: fn (array $input): array => ['valid' => false, 'errors' => ['Validation failed']],
        );

        $result = $executor->execute([]);

        $this->assertFalse($handlerCalled);
        $this->assertFalse($result->success);
        $this->assertNull($result->result);
        $this->assertSame(['Validation failed'], $result->errors);
    }

    public function test_execute_calls_handler_when_validation_passes(): void
    {
        $handlerCalled = false;
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: function (array $input) use (&$handlerCalled): string {
                $handlerCalled = true;

                return 'result';
            },
            validateInputHandler: fn (array $input): array => ['valid' => true, 'errors' => []],
        );

        $result = $executor->execute([]);

        $this->assertTrue($handlerCalled);
        $this->assertTrue($result->success);
    }

    public function test_to_tool_definition_returns_correct_structure(): void
    {
        $parameters = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
        ];

        $executor = new CallableExecutor(
            name: 'greet',
            description: 'Greet a person',
            handler: fn (array $input): string => "Hello, {$input['name']}!",
            parameters: $parameters,
        );

        $definition = $executor->toToolDefinition();

        $this->assertInstanceOf(ToolDefinition::class, $definition);
        $this->assertSame('greet', $definition->name);
        $this->assertSame('Greet a person', $definition->description);
        $this->assertSame($parameters, $definition->parameters);
    }

    public function test_to_tool_definition_with_empty_parameters(): void
    {
        $executor = new CallableExecutor(
            name: 'no_params',
            description: 'Tool with no params',
            handler: fn (array $input): string => 'result',
        );

        $definition = $executor->toToolDefinition();

        $this->assertSame([], $definition->parameters);
    }

    public function test_get_name(): void
    {
        $executor = new CallableExecutor(
            name: 'my_tool',
            description: 'desc',
            handler: fn (array $input): null => null,
        );

        $this->assertSame('my_tool', $executor->getName());
    }

    public function test_get_description(): void
    {
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'My tool description',
            handler: fn (array $input): null => null,
        );

        $this->assertSame('My tool description', $executor->getDescription());
    }

    public function test_get_parameters_returns_empty_array_by_default(): void
    {
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: fn (array $input): null => null,
        );

        $this->assertSame([], $executor->getParameters());
    }

    public function test_get_attributes_returns_empty_array_by_default(): void
    {
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: fn (array $input): null => null,
        );

        $this->assertSame([], $executor->getAttributes());
    }

    public function test_execute_returns_array_result(): void
    {
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: fn (array $input): array => ['key' => 'value', 'nested' => ['a' => 1]],
        );

        $result = $executor->execute([]);

        $this->assertTrue($result->success);
        $this->assertSame(['key' => 'value', 'nested' => ['a' => 1]], $result->result);
    }

    public function test_execute_returns_null_result(): void
    {
        $executor = new CallableExecutor(
            name: 'tool',
            description: 'desc',
            handler: fn (array $input): null => null,
        );

        $result = $executor->execute([]);

        $this->assertTrue($result->success);
        $this->assertNull($result->result);
    }
}
