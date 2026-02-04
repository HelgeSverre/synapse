<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Executor;

use HelgeSverre\Synapse\Executor\ExecutionResult;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\State\ConversationState;
use PHPUnit\Framework\TestCase;

final class ExecutionResultTest extends TestCase
{
    private function createResponse(string $text = 'test'): GenerationResponse
    {
        return new GenerationResponse(
            text: $text,
            messages: [],
            toolCalls: [],
            model: 'test-model',
        );
    }

    public function test_constructor_and_get_value_with_string(): void
    {
        $state = new ConversationState;
        $response = $this->createResponse();

        $result = new ExecutionResult(
            value: 'hello world',
            state: $state,
            response: $response,
        );

        $this->assertSame('hello world', $result->getValue());
        $this->assertSame('hello world', $result->value);
    }

    public function test_constructor_and_get_value_with_array(): void
    {
        $state = new ConversationState;
        $response = $this->createResponse();
        $value = ['key' => 'value', 'nested' => ['foo' => 'bar']];

        $result = new ExecutionResult(
            value: $value,
            state: $state,
            response: $response,
        );

        $this->assertSame($value, $result->getValue());
        $this->assertSame($value, $result->value);
    }

    public function test_constructor_and_get_value_with_object(): void
    {
        $state = new ConversationState;
        $response = $this->createResponse();
        $value = new \stdClass;
        $value->name = 'test';
        $value->count = 42;

        $result = new ExecutionResult(
            value: $value,
            state: $state,
            response: $response,
        );

        $this->assertSame($value, $result->getValue());
        $this->assertSame('test', $result->getValue()->name);
        $this->assertSame(42, $result->getValue()->count);
    }

    public function test_constructor_and_get_value_with_null(): void
    {
        $state = new ConversationState;
        $response = $this->createResponse();

        $result = new ExecutionResult(
            value: null,
            state: $state,
            response: $response,
        );

        $this->assertNull($result->getValue());
        $this->assertNull($result->value);
    }

    public function test_get_response(): void
    {
        $state = new ConversationState;
        $response = $this->createResponse('custom response text');

        $result = new ExecutionResult(
            value: 'test',
            state: $state,
            response: $response,
        );

        $this->assertSame($response, $result->response);
        $this->assertSame('custom response text', $result->response->text);
        $this->assertSame('test-model', $result->response->model);
    }

    public function test_get_state(): void
    {
        $state = new ConversationState(
            attributes: ['custom' => 'attribute'],
        );
        $response = $this->createResponse();

        $result = new ExecutionResult(
            value: 'test',
            state: $state,
            response: $response,
        );

        $this->assertSame($state, $result->state);
        $this->assertSame('attribute', $result->state->getAttribute('custom'));
    }

    public function test_get_metadata_with_default_empty_array(): void
    {
        $state = new ConversationState;
        $response = $this->createResponse();

        $result = new ExecutionResult(
            value: 'test',
            state: $state,
            response: $response,
        );

        $this->assertSame([], $result->metadata);
    }

    public function test_get_metadata_with_custom_values(): void
    {
        $state = new ConversationState;
        $response = $this->createResponse();
        $metadata = [
            'execution_time' => 1.5,
            'provider' => 'openai',
            'custom_key' => ['nested' => 'value'],
        ];

        $result = new ExecutionResult(
            value: 'test',
            state: $state,
            response: $response,
            metadata: $metadata,
        );

        $this->assertSame($metadata, $result->metadata);
        $this->assertSame(1.5, $result->metadata['execution_time']);
        $this->assertSame('openai', $result->metadata['provider']);
        $this->assertSame(['nested' => 'value'], $result->metadata['custom_key']);
    }

    public function test_value_with_integer(): void
    {
        $state = new ConversationState;
        $response = $this->createResponse();

        $result = new ExecutionResult(
            value: 42,
            state: $state,
            response: $response,
        );

        $this->assertSame(42, $result->getValue());
    }

    public function test_value_with_boolean(): void
    {
        $state = new ConversationState;
        $response = $this->createResponse();

        $result = new ExecutionResult(
            value: true,
            state: $state,
            response: $response,
        );

        $this->assertTrue($result->getValue());
    }

    public function test_value_with_float(): void
    {
        $state = new ConversationState;
        $response = $this->createResponse();

        $result = new ExecutionResult(
            value: 3.14,
            state: $state,
            response: $response,
        );

        $this->assertSame(3.14, $result->getValue());
    }

    public function test_is_readonly(): void
    {
        $state = new ConversationState;
        $response = $this->createResponse();

        $result = new ExecutionResult(
            value: 'test',
            state: $state,
            response: $response,
            metadata: ['key' => 'value'],
        );

        $reflection = new \ReflectionClass($result);
        $this->assertTrue($reflection->isReadOnly());
    }
}
