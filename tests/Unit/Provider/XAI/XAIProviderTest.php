<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Provider\XAI;

use LlmExe\Provider\Http\TransportInterface;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\Provider\Request\ToolDefinition;
use LlmExe\Provider\XAI\XAIProvider;
use LlmExe\State\Message;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class XAIProviderTest extends TestCase
{
    private TransportInterface&MockObject $transport;

    private XAIProvider $provider;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->provider = new XAIProvider($this->transport, 'test-api-key');
    }

    public function test_get_name_returns_xai(): void
    {
        $this->assertSame('xai', $this->provider->getName());
    }

    public function test_get_capabilities_returns_correct_values(): void
    {
        $capabilities = $this->provider->getCapabilities();

        $this->assertTrue($capabilities->supportsTools);
        $this->assertTrue($capabilities->supportsJsonMode);
        $this->assertTrue($capabilities->supportsStreaming);
        $this->assertTrue($capabilities->supportsVision);
        $this->assertTrue($capabilities->supportsSystemPrompt);
    }

    public function test_generate_sends_request_to_correct_url(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                'https://api.x.ai/v1/chat/completions',
                $this->anything(),
                $this->anything(),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [Message::user('Hello')],
        );

        $this->provider->generate($request);
    }

    public function test_generate_uses_custom_base_url(): void
    {
        $provider = new XAIProvider($this->transport, 'test-api-key', 'https://custom.api.x.ai/v1');

        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                'https://custom.api.x.ai/v1/chat/completions',
                $this->anything(),
                $this->anything(),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [Message::user('Hello')],
        );

        $provider->generate($request);
    }

    public function test_generate_sends_bearer_auth_header(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $headers): bool {
                    return isset($headers['Authorization'])
                        && $headers['Authorization'] === 'Bearer test-api-key'
                        && isset($headers['Content-Type'])
                        && $headers['Content-Type'] === 'application/json';
                }),
                $this->anything(),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [Message::user('Hello')],
        );

        $this->provider->generate($request);
    }

    public function test_generate_builds_request_with_messages(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $body): bool {
                    return $body['model'] === 'grok-beta'
                        && count($body['messages']) === 1
                        && $body['messages'][0]['role'] === 'user'
                        && $body['messages'][0]['content'] === 'Hello';
                }),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [Message::user('Hello')],
        );

        $this->provider->generate($request);
    }

    public function test_generate_includes_system_prompt(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $body): bool {
                    return count($body['messages']) === 2
                        && $body['messages'][0]['role'] === 'system'
                        && $body['messages'][0]['content'] === 'You are helpful'
                        && $body['messages'][1]['role'] === 'user';
                }),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [Message::user('Hello')],
            systemPrompt: 'You are helpful',
        );

        $this->provider->generate($request);
    }

    public function test_generate_includes_optional_parameters(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $body): bool {
                    return $body['temperature'] === 0.7
                        && $body['max_tokens'] === 1000
                        && $body['top_p'] === 0.9
                        && $body['stop'] === ['END'];
                }),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [Message::user('Hello')],
            temperature: 0.7,
            maxTokens: 1000,
            topP: 0.9,
            stopSequences: ['END'],
        );

        $this->provider->generate($request);
    }

    public function test_generate_includes_tools_in_openai_format(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $body): bool {
                    return isset($body['tools'])
                        && count($body['tools']) === 1
                        && $body['tools'][0]['type'] === 'function'
                        && $body['tools'][0]['function']['name'] === 'get_weather'
                        && $body['tools'][0]['function']['description'] === 'Get weather';
                }),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [Message::user('What is the weather?')],
            tools: [new ToolDefinition('get_weather', 'Get weather', ['type' => 'object'])],
        );

        $this->provider->generate($request);
    }

    public function test_generate_includes_tool_choice(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $body): bool {
                    return isset($body['tool_choice']) && $body['tool_choice'] === 'auto';
                }),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [Message::user('What is the weather?')],
            tools: [new ToolDefinition('get_weather', 'Get weather')],
            toolChoice: 'auto',
        );

        $this->provider->generate($request);
    }

    public function test_generate_includes_response_format(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $body): bool {
                    return isset($body['response_format'])
                        && $body['response_format']['type'] === 'json_object';
                }),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [Message::user('Return JSON')],
            responseFormat: ['type' => 'json_object'],
        );

        $this->provider->generate($request);
    }

    public function test_generate_includes_message_name_and_tool_call_id(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $body): bool {
                    $toolMessage = $body['messages'][1] ?? null;

                    return $toolMessage !== null
                        && $toolMessage['role'] === 'tool'
                        && $toolMessage['tool_call_id'] === 'call_123'
                        && $toolMessage['name'] === 'get_weather';
                }),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [
                Message::user('What is the weather?'),
                Message::tool('Sunny', 'call_123', 'get_weather'),
            ],
        );

        $this->provider->generate($request);
    }

    public function test_generate_parses_text_response(): void
    {
        $this->transport
            ->method('post')
            ->willReturn([
                'id' => 'chatcmpl-123',
                'model' => 'grok-beta',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello, how can I help?',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 20,
                    'total_tokens' => 30,
                ],
            ]);

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [Message::user('Hello')],
        );

        $response = $this->provider->generate($request);

        $this->assertSame('Hello, how can I help?', $response->text);
        $this->assertSame('grok-beta', $response->model);
        $this->assertSame('stop', $response->finishReason);
        $this->assertCount(1, $response->messages);
        $this->assertEmpty($response->toolCalls);
    }

    public function test_generate_parses_usage_info(): void
    {
        $this->transport
            ->method('post')
            ->willReturn([
                'model' => 'grok-beta',
                'choices' => [
                    [
                        'message' => ['content' => 'Hello'],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 15,
                    'completion_tokens' => 25,
                    'total_tokens' => 40,
                ],
            ]);

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [Message::user('Hello')],
        );

        $response = $this->provider->generate($request);

        $this->assertNotNull($response->usage);
        $this->assertSame(15, $response->usage->inputTokens);
        $this->assertSame(25, $response->usage->outputTokens);
        $this->assertSame(40, $response->usage->totalTokens);
    }

    public function test_generate_parses_tool_calls(): void
    {
        $this->transport
            ->method('post')
            ->willReturn([
                'model' => 'grok-beta',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [
                                [
                                    'id' => 'call_abc123',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'get_weather',
                                        'arguments' => '{"location":"San Francisco"}',
                                    ],
                                ],
                            ],
                        ],
                        'finish_reason' => 'tool_calls',
                    ],
                ],
            ]);

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [Message::user('What is the weather in SF?')],
            tools: [new ToolDefinition('get_weather', 'Get weather')],
        );

        $response = $this->provider->generate($request);

        $this->assertNull($response->text);
        $this->assertCount(1, $response->toolCalls);
        $this->assertSame('call_abc123', $response->toolCalls[0]->id);
        $this->assertSame('get_weather', $response->toolCalls[0]->name);
        $this->assertSame(['location' => 'San Francisco'], $response->toolCalls[0]->arguments);
    }

    public function test_generate_parses_multiple_tool_calls(): void
    {
        $this->transport
            ->method('post')
            ->willReturn([
                'model' => 'grok-beta',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Checking weather...',
                            'tool_calls' => [
                                [
                                    'id' => 'call_1',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'get_weather',
                                        'arguments' => '{"location":"NYC"}',
                                    ],
                                ],
                                [
                                    'id' => 'call_2',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'get_weather',
                                        'arguments' => '{"location":"LA"}',
                                    ],
                                ],
                            ],
                        ],
                        'finish_reason' => 'tool_calls',
                    ],
                ],
            ]);

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [Message::user('Weather in NYC and LA?')],
        );

        $response = $this->provider->generate($request);

        $this->assertSame('Checking weather...', $response->text);
        $this->assertCount(2, $response->toolCalls);
        $this->assertSame('call_1', $response->toolCalls[0]->id);
        $this->assertSame('call_2', $response->toolCalls[1]->id);
    }

    public function test_generate_handles_empty_choices(): void
    {
        $this->transport
            ->method('post')
            ->willReturn([
                'model' => 'grok-beta',
                'choices' => [],
            ]);

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [Message::user('Hello')],
        );

        $response = $this->provider->generate($request);

        $this->assertNull($response->text);
        $this->assertEmpty($response->messages);
        $this->assertEmpty($response->toolCalls);
    }

    public function test_generate_preserves_raw_response(): void
    {
        $rawResponse = [
            'id' => 'chatcmpl-test',
            'model' => 'grok-beta',
            'choices' => [
                ['message' => ['content' => 'Hi'], 'finish_reason' => 'stop'],
            ],
        ];

        $this->transport
            ->method('post')
            ->willReturn($rawResponse);

        $request = new GenerationRequest(
            model: 'grok-beta',
            messages: [Message::user('Hello')],
        );

        $response = $this->provider->generate($request);

        $this->assertSame($rawResponse, $response->raw);
    }

    /** @return array<string, mixed> */
    private function createBasicResponse(): array
    {
        return [
            'model' => 'grok-beta',
            'choices' => [
                [
                    'message' => ['content' => 'Response'],
                    'finish_reason' => 'stop',
                ],
            ],
        ];
    }
}
