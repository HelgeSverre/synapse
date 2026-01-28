<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Provider\Anthropic;

use LlmExe\Provider\Anthropic\AnthropicProvider;
use LlmExe\Provider\Http\TransportInterface;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\Provider\Request\ToolDefinition;
use LlmExe\State\Message;
use PHPUnit\Framework\TestCase;

final class AnthropicProviderTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $mockTransport;

    protected function setUp(): void
    {
        $this->mockTransport = $this->createMock(TransportInterface::class);
    }

    public function test_basic_text_generation_calls_correct_url(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                'https://api.anthropic.com/v1/messages',
                $this->anything(),
                $this->anything(),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
        );

        $provider->generate($request);
    }

    public function test_basic_text_generation_sets_correct_headers(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(fn (array $headers): bool => $headers['x-api-key'] === 'test-api-key'
                    && $headers['anthropic-version'] === '2023-06-01'
                    && $headers['Content-Type'] === 'application/json'),
                $this->anything(),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
        );

        $provider->generate($request);
    }

    public function test_basic_text_generation_sets_model_and_max_tokens_in_body(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body): bool => $body['model'] === 'claude-3-sonnet'
                    && $body['max_tokens'] === 1000),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
            maxTokens: 1000,
        );

        $provider->generate($request);
    }

    public function test_basic_text_generation_uses_default_max_tokens(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body): bool => $body['max_tokens'] === 4096),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
        );

        $provider->generate($request);
    }

    public function test_response_text_extracted_from_content_blocks(): void
    {
        $this->mockTransport
            ->method('post')
            ->willReturn([
                'content' => [
                    ['type' => 'text', 'text' => 'Hello '],
                    ['type' => 'text', 'text' => 'World!'],
                ],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
        );

        $response = $provider->generate($request);

        $this->assertSame('Hello World!', $response->text);
    }

    public function test_system_prompt_from_request_system_prompt(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body): bool => $body['system'] === 'You are a helpful assistant.'),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
            systemPrompt: 'You are a helpful assistant.',
        );

        $provider->generate($request);
    }

    public function test_system_prompt_extracted_from_system_message(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body): bool => $body['system'] === 'You are a helpful assistant.'
                    && ! $this->containsSystemMessage($body['messages'])),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [
                Message::system('You are a helpful assistant.'),
                Message::user('Hi'),
            ],
        );

        $provider->generate($request);
    }

    public function test_system_prompt_from_request_takes_precedence(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body): bool => $body['system'] === 'Request system prompt'),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [
                Message::system('Message system prompt'),
                Message::user('Hi'),
            ],
            systemPrompt: 'Request system prompt',
        );

        $provider->generate($request);
    }

    public function test_user_messages_converted_correctly(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body): bool => count($body['messages']) === 1
                    && $body['messages'][0]['role'] === 'user'
                    && $body['messages'][0]['content'] === 'Hello'),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hi!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hello')],
        );

        $provider->generate($request);
    }

    public function test_assistant_messages_converted_correctly(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body): bool => count($body['messages']) === 2
                    && $body['messages'][0]['role'] === 'user'
                    && $body['messages'][1]['role'] === 'assistant'),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello again!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [
                Message::user('Hello'),
                Message::assistant('Hi there!'),
            ],
        );

        $provider->generate($request);
    }

    public function test_tool_results_formatted_as_tool_result_blocks(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $body): bool {
                    $toolMessage = $body['messages'][2] ?? null;
                    if ($toolMessage === null) {
                        return false;
                    }

                    return $toolMessage['role'] === 'user'
                        && is_array($toolMessage['content'])
                        && $toolMessage['content'][0]['type'] === 'tool_result'
                        && $toolMessage['content'][0]['tool_use_id'] === 'call_123'
                        && $toolMessage['content'][0]['content'] === '{"result": 42}';
                }),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'The result is 42.']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [
                Message::user('Calculate 6 * 7'),
                Message::assistant('Let me calculate that for you.'),
                Message::tool('{"result": 42}', 'call_123', 'calculator'),
            ],
        );

        $provider->generate($request);
    }

    public function test_temperature_passed_when_set(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body): bool => $body['temperature'] === 0.7),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
            temperature: 0.7,
        );

        $provider->generate($request);
    }

    public function test_top_p_passed_when_set(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body): bool => $body['top_p'] === 0.9),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
            topP: 0.9,
        );

        $provider->generate($request);
    }

    public function test_stop_sequences_passed_when_set(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body): bool => $body['stop_sequences'] === ['STOP', 'END']),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
            stopSequences: ['STOP', 'END'],
        );

        $provider->generate($request);
    }

    public function test_tools_converted_to_anthropic_format(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $body): bool {
                    if (! isset($body['tools']) || count($body['tools']) !== 1) {
                        return false;
                    }

                    $tool = $body['tools'][0];

                    return $tool['name'] === 'calculator'
                        && $tool['description'] === 'Performs calculations'
                        && isset($tool['input_schema']);
                }),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Let me help.']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Calculate 5 + 3')],
            tools: [
                new ToolDefinition(
                    name: 'calculator',
                    description: 'Performs calculations',
                    parameters: [
                        'type' => 'object',
                        'properties' => [
                            'expression' => ['type' => 'string'],
                        ],
                    ],
                ),
            ],
        );

        $provider->generate($request);
    }

    public function test_tool_choice_wrapped_in_type_object(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body): bool => isset($body['tool_choice'])
                    && $body['tool_choice'] === ['type' => 'auto']),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
            tools: [
                new ToolDefinition(
                    name: 'calculator',
                    description: 'Performs calculations',
                ),
            ],
            toolChoice: 'auto',
        );

        $provider->generate($request);
    }

    public function test_tool_use_blocks_parsed_from_response(): void
    {
        $this->mockTransport
            ->method('post')
            ->willReturn([
                'content' => [
                    ['type' => 'text', 'text' => 'Let me calculate that.'],
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_123',
                        'name' => 'calculator',
                        'input' => ['expression' => '5 + 3'],
                    ],
                ],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Calculate 5 + 3')],
            tools: [
                new ToolDefinition(
                    name: 'calculator',
                    description: 'Performs calculations',
                ),
            ],
        );

        $response = $provider->generate($request);

        $this->assertCount(1, $response->toolCalls);
        $this->assertSame('toolu_123', $response->toolCalls[0]->id);
        $this->assertSame('calculator', $response->toolCalls[0]->name);
        $this->assertSame(['expression' => '5 + 3'], $response->toolCalls[0]->arguments);
    }

    public function test_usage_info_parsed_correctly(): void
    {
        $this->mockTransport
            ->method('post')
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 5,
                ],
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
        );

        $response = $provider->generate($request);

        $this->assertNotNull($response->usage);
        $this->assertSame(10, $response->usage->inputTokens);
        $this->assertSame(5, $response->usage->outputTokens);
    }

    public function test_get_capabilities_supports_json_mode_is_false(): void
    {
        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');

        $capabilities = $provider->getCapabilities();

        $this->assertFalse($capabilities->supportsJsonMode);
    }

    public function test_get_capabilities_supports_tools(): void
    {
        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');

        $capabilities = $provider->getCapabilities();

        $this->assertTrue($capabilities->supportsTools);
    }

    public function test_get_capabilities_supports_streaming(): void
    {
        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');

        $capabilities = $provider->getCapabilities();

        $this->assertTrue($capabilities->supportsStreaming);
    }

    public function test_get_capabilities_supports_vision(): void
    {
        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');

        $capabilities = $provider->getCapabilities();

        $this->assertTrue($capabilities->supportsVision);
    }

    public function test_get_capabilities_supports_system_prompt(): void
    {
        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');

        $capabilities = $provider->getCapabilities();

        $this->assertTrue($capabilities->supportsSystemPrompt);
    }

    public function test_get_name_returns_anthropic(): void
    {
        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');

        $this->assertSame('anthropic', $provider->getName());
    }

    public function test_custom_base_url_used(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                'https://custom.api.com/v1/messages',
                $this->anything(),
                $this->anything(),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider(
            $this->mockTransport,
            'test-api-key',
            'https://custom.api.com/v1',
        );
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
        );

        $provider->generate($request);
    }

    public function test_custom_api_version_used(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(fn (array $headers): bool => $headers['anthropic-version'] === '2024-01-01'),
                $this->anything(),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider(
            $this->mockTransport,
            'test-api-key',
            'https://api.anthropic.com/v1',
            '2024-01-01',
        );
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
        );

        $provider->generate($request);
    }

    public function test_response_includes_finish_reason(): void
    {
        $this->mockTransport
            ->method('post')
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
                'stop_reason' => 'end_turn',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
        );

        $response = $provider->generate($request);

        $this->assertSame('end_turn', $response->finishReason);
    }

    public function test_response_includes_model(): void
    {
        $this->mockTransport
            ->method('post')
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet-20240229',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
        );

        $response = $provider->generate($request);

        $this->assertSame('claude-3-sonnet-20240229', $response->model);
    }

    public function test_response_includes_raw_response(): void
    {
        $rawResponse = [
            'content' => [['type' => 'text', 'text' => 'Hello!']],
            'model' => 'claude-3-sonnet',
            'id' => 'msg_123',
        ];

        $this->mockTransport
            ->method('post')
            ->willReturn($rawResponse);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
        );

        $response = $provider->generate($request);

        $this->assertSame($rawResponse, $response->raw);
    }

    public function test_response_includes_assistant_message(): void
    {
        $this->mockTransport
            ->method('post')
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
        );

        $response = $provider->generate($request);

        $this->assertCount(1, $response->messages);
        $this->assertSame('Hello!', $response->messages[0]->content);
    }

    public function test_tool_use_without_input_defaults_to_empty_array(): void
    {
        $this->mockTransport
            ->method('post')
            ->willReturn([
                'content' => [
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_123',
                        'name' => 'get_time',
                    ],
                ],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('What time is it?')],
        );

        $response = $provider->generate($request);

        $this->assertCount(1, $response->toolCalls);
        $this->assertSame([], $response->toolCalls[0]->arguments);
    }

    public function test_optional_parameters_not_included_when_null(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body): bool => ! array_key_exists('temperature', $body)
                    && ! array_key_exists('top_p', $body)
                    && ! array_key_exists('stop_sequences', $body)
                    && ! array_key_exists('system', $body)
                    && ! array_key_exists('tools', $body)
                    && ! array_key_exists('tool_choice', $body)),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
        );

        $provider->generate($request);
    }

    public function test_tool_choice_not_set_when_null_with_tools(): void
    {
        $this->mockTransport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body): bool => isset($body['tools'])
                    && ! array_key_exists('tool_choice', $body)),
            )
            ->willReturn([
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'model' => 'claude-3-sonnet',
            ]);

        $provider = new AnthropicProvider($this->mockTransport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'claude-3-sonnet',
            messages: [Message::user('Hi')],
            tools: [
                new ToolDefinition(
                    name: 'test',
                    description: 'Test tool',
                ),
            ],
        );

        $provider->generate($request);
    }

    /** @param list<array{role: string, content: mixed}> $messages */
    private function containsSystemMessage(array $messages): bool
    {
        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                return true;
            }
        }

        return false;
    }
}
