<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Provider\OpenAI;

use HelgeSverre\Synapse\Provider\Http\TransportInterface;
use HelgeSverre\Synapse\Provider\OpenAI\OpenAIProvider;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Request\ToolDefinition;
use HelgeSverre\Synapse\State\Message;
use PHPUnit\Framework\TestCase;

final class MockTransport implements TransportInterface
{
    public string $capturedUrl = '';

    /** @var array<string, string> */
    public array $capturedHeaders = [];

    /** @var array<string, mixed> */
    public array $capturedBody = [];

    public function __construct(
        private readonly array $response,
    ) {}

    public function post(string $url, array $headers, array $body): array
    {
        $this->capturedUrl = $url;
        $this->capturedHeaders = $headers;
        $this->capturedBody = $body;

        return $this->response;
    }
}

final class OpenAIProviderTest extends TestCase
{
    private function createMockTransport(array $response): MockTransport
    {
        return new MockTransport($response);
    }

    private function createBasicResponse(string $content = 'Hello!'): array
    {
        return [
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => $content,
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
                'total_tokens' => 15,
            ],
        ];
    }

    public function test_generate_calls_correct_url(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
        );

        $provider->generate($request);

        $this->assertSame('https://api.openai.com/v1/chat/completions', $transport->capturedUrl);
    }

    public function test_generate_sets_authorization_header(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
        );

        $provider->generate($request);

        $this->assertSame('Bearer sk-test-key', $transport->capturedHeaders['Authorization']);
        $this->assertSame('application/json', $transport->capturedHeaders['Content-Type']);
    }

    public function test_generate_passes_model_in_body(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4-turbo',
            messages: [Message::user('Hello')],
        );

        $provider->generate($request);

        $this->assertSame('gpt-4-turbo', $transport->capturedBody['model']);
    }

    public function test_generate_extracts_response_text(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse('The answer is 42.'));
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('What is the answer?')],
        );

        $response = $provider->generate($request);

        $this->assertSame('The answer is 42.', $response->text);
    }

    public function test_generate_parses_usage_info(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
        );

        $response = $provider->generate($request);

        $this->assertNotNull($response->usage);
        $this->assertSame(10, $response->usage->inputTokens);
        $this->assertSame(5, $response->usage->outputTokens);
        $this->assertSame(15, $response->usage->totalTokens);
    }

    public function test_generate_includes_system_prompt_as_first_message(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
            systemPrompt: 'You are a helpful assistant.',
        );

        $provider->generate($request);

        $this->assertCount(2, $transport->capturedBody['messages']);
        $this->assertSame('system', $transport->capturedBody['messages'][0]['role']);
        $this->assertSame('You are a helpful assistant.', $transport->capturedBody['messages'][0]['content']);
    }

    public function test_generate_converts_user_message(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('What is 2+2?')],
        );

        $provider->generate($request);

        $this->assertSame('user', $transport->capturedBody['messages'][0]['role']);
        $this->assertSame('What is 2+2?', $transport->capturedBody['messages'][0]['content']);
    }

    public function test_generate_converts_assistant_message(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [
                Message::user('Hi'),
                Message::assistant('Hello!'),
                Message::user('How are you?'),
            ],
        );

        $provider->generate($request);

        $this->assertSame('assistant', $transport->capturedBody['messages'][1]['role']);
        $this->assertSame('Hello!', $transport->capturedBody['messages'][1]['content']);
    }

    public function test_generate_includes_message_name_when_present(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello', 'John')],
        );

        $provider->generate($request);

        $this->assertSame('John', $transport->capturedBody['messages'][0]['name']);
    }

    public function test_generate_includes_tool_call_id_when_present(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::tool('{"result": 42}', 'call_abc123', 'calculator')],
        );

        $provider->generate($request);

        $this->assertSame('call_abc123', $transport->capturedBody['messages'][0]['tool_call_id']);
    }

    public function test_generate_passes_temperature_when_set(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
            temperature: 0.7,
        );

        $provider->generate($request);

        $this->assertSame(0.7, $transport->capturedBody['temperature']);
    }

    public function test_generate_passes_max_tokens_when_set(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
            maxTokens: 1000,
        );

        $provider->generate($request);

        $this->assertSame(1000, $transport->capturedBody['max_tokens']);
    }

    public function test_generate_passes_top_p_when_set(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
            topP: 0.9,
        );

        $provider->generate($request);

        $this->assertSame(0.9, $transport->capturedBody['top_p']);
    }

    public function test_generate_passes_stop_sequences_when_set(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
            stopSequences: ['STOP', 'END'],
        );

        $provider->generate($request);

        $this->assertSame(['STOP', 'END'], $transport->capturedBody['stop']);
    }

    public function test_generate_does_not_include_optional_params_when_null(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
        );

        $provider->generate($request);

        $this->assertArrayNotHasKey('temperature', $transport->capturedBody);
        $this->assertArrayNotHasKey('max_tokens', $transport->capturedBody);
        $this->assertArrayNotHasKey('top_p', $transport->capturedBody);
        $this->assertArrayNotHasKey('stop', $transport->capturedBody);
    }

    public function test_generate_converts_tools_to_openai_format(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $tool = new ToolDefinition(
            name: 'get_weather',
            description: 'Get the current weather',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'location' => ['type' => 'string', 'description' => 'The city name'],
                ],
                'required' => ['location'],
            ],
        );

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('What is the weather in Paris?')],
            tools: [$tool],
        );

        $provider->generate($request);

        $this->assertArrayHasKey('tools', $transport->capturedBody);
        $this->assertCount(1, $transport->capturedBody['tools']);
        $this->assertSame('function', $transport->capturedBody['tools'][0]['type']);
        $this->assertSame('get_weather', $transport->capturedBody['tools'][0]['function']['name']);
        $this->assertSame('Get the current weather', $transport->capturedBody['tools'][0]['function']['description']);
    }

    public function test_generate_passes_tool_choice_when_set(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $tool = new ToolDefinition('get_weather', 'Get weather');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('What is the weather?')],
            tools: [$tool],
            toolChoice: 'auto',
        );

        $provider->generate($request);

        $this->assertSame('auto', $transport->capturedBody['tool_choice']);
    }

    public function test_generate_does_not_include_tool_choice_without_tools(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
            toolChoice: 'auto',
        );

        $provider->generate($request);

        $this->assertArrayNotHasKey('tool_choice', $transport->capturedBody);
        $this->assertArrayNotHasKey('tools', $transport->capturedBody);
    }

    public function test_generate_parses_tool_calls_from_response(): void
    {
        $response = [
            'id' => 'chatcmpl-123',
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_abc123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_weather',
                                    'arguments' => '{"location": "Paris"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
                'total_tokens' => 15,
            ],
        ];
        $transport = $this->createMockTransport($response);
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('What is the weather in Paris?')],
        );

        $result = $provider->generate($request);

        $this->assertCount(1, $result->toolCalls);
        $this->assertSame('call_abc123', $result->toolCalls[0]->id);
        $this->assertSame('get_weather', $result->toolCalls[0]->name);
        $this->assertSame(['location' => 'Paris'], $result->toolCalls[0]->arguments);
    }

    public function test_generate_parses_multiple_tool_calls(): void
    {
        $response = [
            'id' => 'chatcmpl-123',
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_weather',
                                    'arguments' => '{"location": "Paris"}',
                                ],
                            ],
                            [
                                'id' => 'call_2',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_weather',
                                    'arguments' => '{"location": "London"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
                'total_tokens' => 15,
            ],
        ];
        $transport = $this->createMockTransport($response);
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Weather in Paris and London?')],
        );

        $result = $provider->generate($request);

        $this->assertCount(2, $result->toolCalls);
        $this->assertSame('call_1', $result->toolCalls[0]->id);
        $this->assertSame('call_2', $result->toolCalls[1]->id);
    }

    public function test_generate_passes_response_format_when_set(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse('{"result": true}'));
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Return JSON')],
            responseFormat: ['type' => 'json_object'],
        );

        $provider->generate($request);

        $this->assertSame(['type' => 'json_object'], $transport->capturedBody['response_format']);
    }

    public function test_get_capabilities_returns_correct_values(): void
    {
        $transport = $this->createMockTransport([]);
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $capabilities = $provider->getCapabilities();

        $this->assertTrue($capabilities->supportsTools);
        $this->assertTrue($capabilities->supportsJsonMode);
        $this->assertTrue($capabilities->supportsStreaming);
        $this->assertTrue($capabilities->supportsVision);
        $this->assertTrue($capabilities->supportsSystemPrompt);
    }

    public function test_get_name_returns_openai(): void
    {
        $transport = $this->createMockTransport([]);
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $this->assertSame('openai', $provider->getName());
    }

    public function test_custom_base_url_is_used(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider(
            $transport,
            'sk-test-key',
            'https://custom.openai.example.com/v1',
        );

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
        );

        $provider->generate($request);

        $this->assertSame('https://custom.openai.example.com/v1/chat/completions', $transport->capturedUrl);
    }

    public function test_generate_returns_finish_reason(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
        );

        $response = $provider->generate($request);

        $this->assertSame('stop', $response->finishReason);
    }

    public function test_generate_returns_model_from_response(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
        );

        $response = $provider->generate($request);

        $this->assertSame('gpt-4', $response->model);
    }

    public function test_generate_includes_raw_response(): void
    {
        $rawResponse = $this->createBasicResponse();
        $transport = $this->createMockTransport($rawResponse);
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
        );

        $response = $provider->generate($request);

        $this->assertSame($rawResponse, $response->raw);
    }

    public function test_generate_creates_assistant_message_in_response(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse('Test response'));
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
        );

        $response = $provider->generate($request);

        $this->assertCount(1, $response->messages);
        $this->assertSame('assistant', $response->messages[0]->role->value);
        $this->assertSame('Test response', $response->messages[0]->content);
    }

    public function test_generate_handles_null_content_with_tool_calls(): void
    {
        $response = [
            'id' => 'chatcmpl-123',
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'test',
                                    'arguments' => '{}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
                'total_tokens' => 15,
            ],
        ];
        $transport = $this->createMockTransport($response);
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Call a tool')],
        );

        $result = $provider->generate($request);

        $this->assertNull($result->text);
        $this->assertCount(1, $result->toolCalls);
        $this->assertCount(1, $result->messages);
    }

    public function test_generate_handles_empty_response(): void
    {
        $response = [
            'id' => 'chatcmpl-123',
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ];
        $transport = $this->createMockTransport($response);
        $provider = new OpenAIProvider($transport, 'sk-test-key');

        $request = new GenerationRequest(
            model: 'gpt-4',
            messages: [Message::user('Hello')],
        );

        $result = $provider->generate($request);

        $this->assertNull($result->text);
        $this->assertNull($result->usage);
    }
}
