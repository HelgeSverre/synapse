<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Provider\Bedrock;

use LlmExe\Provider\Bedrock\BedrockProvider;
use LlmExe\Provider\Http\TransportInterface;
use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\Provider\Request\ToolDefinition;
use LlmExe\State\Message;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class BedrockProviderTest extends TestCase
{
    private TransportInterface&MockObject $transport;

    private BedrockProvider $provider;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->provider = new BedrockProvider($this->transport, 'us-east-1');
    }

    public function test_get_name_returns_bedrock(): void
    {
        $this->assertSame('bedrock', $this->provider->getName());
    }

    public function test_get_capabilities_returns_correct_values(): void
    {
        $capabilities = $this->provider->getCapabilities();

        $this->assertTrue($capabilities->supportsTools);
        $this->assertFalse($capabilities->supportsJsonMode);
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
                'https://bedrock-runtime.us-east-1.amazonaws.com/model/anthropic.claude-3-sonnet-20240229-v1:0/converse',
                $this->anything(),
                $this->anything(),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'anthropic.claude-3-sonnet-20240229-v1:0',
            messages: [Message::user('Hello')],
        );

        $this->provider->generate($request);
    }

    public function test_generate_uses_custom_base_url(): void
    {
        $provider = new BedrockProvider(
            $this->transport,
            'us-west-2',
            'https://custom-bedrock.example.com',
        );

        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                'https://custom-bedrock.example.com/model/test-model/converse',
                $this->anything(),
                $this->anything(),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('Hello')],
        );

        $provider->generate($request);
    }

    public function test_generate_uses_different_region(): void
    {
        $provider = new BedrockProvider($this->transport, 'eu-west-1');

        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('bedrock-runtime.eu-west-1.amazonaws.com'),
                $this->anything(),
                $this->anything(),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('Hello')],
        );

        $provider->generate($request);
    }

    public function test_generate_sends_correct_headers(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(fn (array $headers): bool => $headers['Content-Type'] === 'application/json'
                    && $headers['Accept'] === 'application/json'),
                $this->anything(),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('Hello')],
        );

        $this->provider->generate($request);
    }

    public function test_generate_builds_messages_in_bedrock_format(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $body): bool {
                    $messages = $body['messages'] ?? [];

                    return count($messages) === 1
                        && $messages[0]['role'] === 'user'
                        && $messages[0]['content'][0]['text'] === 'Hello';
                }),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('Hello')],
        );

        $this->provider->generate($request);
    }

    public function test_generate_includes_system_prompt_as_array_of_text_blocks(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body): bool => isset($body['system'])
                    && is_array($body['system'])
                    && $body['system'][0]['text'] === 'You are helpful'),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('Hello')],
            systemPrompt: 'You are helpful',
        );

        $this->provider->generate($request);
    }

    public function test_generate_includes_inference_config(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $body): bool {
                    $config = $body['inferenceConfig'] ?? [];

                    return $config['temperature'] === 0.7
                        && $config['maxTokens'] === 1000
                        && $config['topP'] === 0.9
                        && $config['stopSequences'] === ['END'];
                }),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('Hello')],
            temperature: 0.7,
            maxTokens: 1000,
            topP: 0.9,
            stopSequences: ['END'],
        );

        $this->provider->generate($request);
    }

    public function test_generate_omits_inference_config_when_no_parameters(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn (array $body): bool => ! isset($body['inferenceConfig'])),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('Hello')],
        );

        $this->provider->generate($request);
    }

    public function test_generate_includes_tools_in_tool_config_format(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $body): bool {
                    $toolConfig = $body['toolConfig'] ?? [];
                    $tools = $toolConfig['tools'] ?? [];

                    return count($tools) === 1
                        && $tools[0]['toolSpec']['name'] === 'get_weather'
                        && $tools[0]['toolSpec']['description'] === 'Get weather'
                        && isset($tools[0]['toolSpec']['inputSchema']['json']);
                }),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('Weather?')],
            tools: [new ToolDefinition('get_weather', 'Get weather', ['type' => 'object'])],
        );

        $this->provider->generate($request);
    }

    public function test_generate_handles_tool_results_as_tool_result_blocks(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $body): bool {
                    $messages = $body['messages'] ?? [];
                    $toolResultMessage = $messages[1] ?? [];

                    return $toolResultMessage['role'] === 'user'
                        && isset($toolResultMessage['content'][0]['toolResult'])
                        && $toolResultMessage['content'][0]['toolResult']['toolUseId'] === 'call_123'
                        && $toolResultMessage['content'][0]['toolResult']['content'][0]['text'] === 'Sunny, 72F';
                }),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [
                Message::user('What is the weather?'),
                Message::tool('Sunny, 72F', 'call_123'),
            ],
        );

        $this->provider->generate($request);
    }

    public function test_generate_parses_text_response_from_output_message_content(): void
    {
        $this->transport
            ->method('post')
            ->willReturn([
                'output' => [
                    'message' => [
                        'role' => 'assistant',
                        'content' => [
                            ['text' => 'Hello, how can I help?'],
                        ],
                    ],
                ],
                'stopReason' => 'end_turn',
                'usage' => [
                    'inputTokens' => 10,
                    'outputTokens' => 20,
                ],
            ]);

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('Hello')],
        );

        $response = $this->provider->generate($request);

        $this->assertSame('Hello, how can I help?', $response->text);
        $this->assertSame('test-model', $response->model);
        $this->assertSame('end_turn', $response->finishReason);
        $this->assertCount(1, $response->messages);
    }

    public function test_generate_parses_usage_info(): void
    {
        $this->transport
            ->method('post')
            ->willReturn([
                'output' => [
                    'message' => [
                        'content' => [['text' => 'Hi']],
                    ],
                ],
                'usage' => [
                    'inputTokens' => 15,
                    'outputTokens' => 25,
                    'totalTokens' => 40,
                ],
            ]);

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('Hello')],
        );

        $response = $this->provider->generate($request);

        $this->assertNotNull($response->usage);
        $this->assertSame(15, $response->usage->inputTokens);
        $this->assertSame(25, $response->usage->outputTokens);
        $this->assertSame(40, $response->usage->totalTokens);
    }

    public function test_generate_parses_tool_use_from_response(): void
    {
        $this->transport
            ->method('post')
            ->willReturn([
                'output' => [
                    'message' => [
                        'role' => 'assistant',
                        'content' => [
                            [
                                'toolUse' => [
                                    'toolUseId' => 'tool_abc123',
                                    'name' => 'get_weather',
                                    'input' => ['location' => 'San Francisco'],
                                ],
                            ],
                        ],
                    ],
                ],
                'stopReason' => 'tool_use',
            ]);

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('Weather in SF?')],
        );

        $response = $this->provider->generate($request);

        $this->assertNull($response->text);
        $this->assertCount(1, $response->toolCalls);
        $this->assertSame('tool_abc123', $response->toolCalls[0]->id);
        $this->assertSame('get_weather', $response->toolCalls[0]->name);
        $this->assertSame(['location' => 'San Francisco'], $response->toolCalls[0]->arguments);
    }

    public function test_generate_parses_mixed_text_and_tool_use(): void
    {
        $this->transport
            ->method('post')
            ->willReturn([
                'output' => [
                    'message' => [
                        'role' => 'assistant',
                        'content' => [
                            ['text' => 'Let me check that for you.'],
                            [
                                'toolUse' => [
                                    'toolUseId' => 'tool_1',
                                    'name' => 'get_weather',
                                    'input' => ['city' => 'NYC'],
                                ],
                            ],
                        ],
                    ],
                ],
                'stopReason' => 'tool_use',
            ]);

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('Weather?')],
        );

        $response = $this->provider->generate($request);

        $this->assertSame('Let me check that for you.', $response->text);
        $this->assertCount(1, $response->toolCalls);
        $this->assertSame('tool_1', $response->toolCalls[0]->id);
    }

    public function test_generate_concatenates_multiple_text_blocks(): void
    {
        $this->transport
            ->method('post')
            ->willReturn([
                'output' => [
                    'message' => [
                        'content' => [
                            ['text' => 'First part. '],
                            ['text' => 'Second part.'],
                        ],
                    ],
                ],
            ]);

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('Hello')],
        );

        $response = $this->provider->generate($request);

        $this->assertSame('First part. Second part.', $response->text);
    }

    public function test_generate_handles_empty_content(): void
    {
        $this->transport
            ->method('post')
            ->willReturn([
                'output' => [
                    'message' => [
                        'content' => [],
                    ],
                ],
            ]);

        $request = new GenerationRequest(
            model: 'test-model',
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
            'output' => [
                'message' => [
                    'content' => [['text' => 'Hi']],
                ],
            ],
            'stopReason' => 'end_turn',
        ];

        $this->transport
            ->method('post')
            ->willReturn($rawResponse);

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('Hello')],
        );

        $response = $this->provider->generate($request);

        $this->assertSame($rawResponse, $response->raw);
    }

    public function test_generate_handles_assistant_messages(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $body): bool {
                    $messages = $body['messages'] ?? [];

                    return count($messages) === 2
                        && $messages[0]['role'] === 'user'
                        && $messages[1]['role'] === 'assistant'
                        && $messages[1]['content'][0]['text'] === 'Previous response';
                }),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [
                Message::user('Hello'),
                Message::assistant('Previous response'),
            ],
        );

        $this->provider->generate($request);
    }

    public function test_generate_tool_with_empty_parameters(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $body): bool {
                    $toolConfig = $body['toolConfig'] ?? [];
                    $tools = $toolConfig['tools'] ?? [];
                    $inputSchema = $tools[0]['toolSpec']['inputSchema']['json'] ?? null;

                    return $inputSchema !== null
                        && $inputSchema['type'] === 'object';
                }),
            )
            ->willReturn($this->createBasicResponse());

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('Run it')],
            tools: [new ToolDefinition('run_task', 'Run a task')],
        );

        $this->provider->generate($request);
    }

    /** @return array<string, mixed> */
    private function createBasicResponse(): array
    {
        return [
            'output' => [
                'message' => [
                    'role' => 'assistant',
                    'content' => [
                        ['text' => 'Response'],
                    ],
                ],
            ],
            'stopReason' => 'end_turn',
        ];
    }
}
