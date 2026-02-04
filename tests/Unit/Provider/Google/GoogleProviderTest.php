<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Provider\Google;

use HelgeSverre\Synapse\Provider\Google\GoogleProvider;
use HelgeSverre\Synapse\Provider\Http\TransportInterface;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Request\ToolDefinition;
use HelgeSverre\Synapse\State\Message;
use PHPUnit\Framework\TestCase;

final class GoogleMockTransport implements TransportInterface
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

final class GoogleProviderTest extends TestCase
{
    private function createMockTransport(array $response): GoogleMockTransport
    {
        return new GoogleMockTransport($response);
    }

    private function createBasicResponse(string $text = 'Hello!'): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 5,
                'totalTokenCount' => 15,
            ],
        ];
    }

    public function test_basic_text_generation(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse('Hello from Gemini!'));

        $provider = new GoogleProvider($transport, 'test-api-key');
        $request = new GenerationRequest(
            model: 'gemini-pro',
            messages: [Message::user('Hello')],
        );

        $response = $provider->generate($request);

        $this->assertStringContainsString('gemini-pro:generateContent', $transport->capturedUrl);
        $this->assertStringContainsString('key=test-api-key', $transport->capturedUrl);
        $this->assertSame('application/json', $transport->capturedHeaders['Content-Type']);
        $this->assertSame('Hello from Gemini!', $response->text);
        $this->assertSame('gemini-pro', $response->model);
        $this->assertSame('STOP', $response->finishReason);
    }

    public function test_usage_metadata_parsing(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());
        $provider = new GoogleProvider($transport, 'test-key');

        $response = $provider->generate(new GenerationRequest(
            model: 'gemini-pro',
            messages: [Message::user('Hi')],
        ));

        $this->assertNotNull($response->usage);
        $this->assertSame(10, $response->usage->inputTokens);
        $this->assertSame(5, $response->usage->outputTokens);
        $this->assertSame(15, $response->usage->totalTokens);
    }

    public function test_system_prompt_handling(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());

        $provider = new GoogleProvider($transport, 'test-key');
        $provider->generate(new GenerationRequest(
            model: 'gemini-pro',
            messages: [Message::user('Hello')],
            systemPrompt: 'You are helpful',
        ));

        $this->assertArrayHasKey('systemInstruction', $transport->capturedBody);
        $this->assertSame('You are helpful', $transport->capturedBody['systemInstruction']['parts'][0]['text']);
    }

    public function test_message_conversion(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());

        $provider = new GoogleProvider($transport, 'test-key');
        $provider->generate(new GenerationRequest(
            model: 'gemini-pro',
            messages: [
                Message::user('Hello'),
                Message::assistant('Hi there'),
            ],
        ));

        $contents = $transport->capturedBody['contents'];
        $this->assertCount(2, $contents);
        $this->assertSame('user', $contents[0]['role']);
        $this->assertSame('Hello', $contents[0]['parts'][0]['text']);
        $this->assertSame('model', $contents[1]['role']);
        $this->assertSame('Hi there', $contents[1]['parts'][0]['text']);
    }

    public function test_generation_config(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());

        $provider = new GoogleProvider($transport, 'test-key');
        $provider->generate(new GenerationRequest(
            model: 'gemini-pro',
            messages: [Message::user('Hello')],
            temperature: 0.7,
            maxTokens: 100,
            topP: 0.9,
            stopSequences: ['STOP'],
        ));

        $this->assertArrayHasKey('generationConfig', $transport->capturedBody);
        $config = $transport->capturedBody['generationConfig'];
        $this->assertSame(0.7, $config['temperature']);
        $this->assertSame(100, $config['maxOutputTokens']);
        $this->assertSame(0.9, $config['topP']);
        $this->assertSame(['STOP'], $config['stopSequences']);
    }

    public function test_tool_definitions(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());

        $provider = new GoogleProvider($transport, 'test-key');
        $provider->generate(new GenerationRequest(
            model: 'gemini-pro',
            messages: [Message::user('What is the weather?')],
            tools: [
                new ToolDefinition(
                    name: 'get_weather',
                    description: 'Get weather info',
                    parameters: ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]],
                ),
            ],
        ));

        $this->assertArrayHasKey('tools', $transport->capturedBody);
        $declarations = $transport->capturedBody['tools'][0]['functionDeclarations'];
        $this->assertCount(1, $declarations);
        $this->assertSame('get_weather', $declarations[0]['name']);
        $this->assertSame('Get weather info', $declarations[0]['description']);
    }

    public function test_tool_call_parsing(): void
    {
        $response = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'functionCall' => [
                                    'name' => 'get_weather',
                                    'args' => ['city' => 'London'],
                                ],
                            ],
                        ],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ];

        $transport = $this->createMockTransport($response);
        $provider = new GoogleProvider($transport, 'test-key');

        $result = $provider->generate(new GenerationRequest(
            model: 'gemini-pro',
            messages: [Message::user('Weather in London?')],
        ));

        $this->assertCount(1, $result->toolCalls);
        $this->assertSame('get_weather', $result->toolCalls[0]->name);
        $this->assertSame(['city' => 'London'], $result->toolCalls[0]->arguments);
    }

    public function test_tool_response_handling(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());

        $provider = new GoogleProvider($transport, 'test-key');
        $provider->generate(new GenerationRequest(
            model: 'gemini-pro',
            messages: [
                Message::user('Weather?'),
                Message::tool('{"temp": 20}', 'call_123', 'get_weather'),
            ],
        ));

        $contents = $transport->capturedBody['contents'];
        $lastContent = end($contents);
        $this->assertArrayHasKey('functionResponse', $lastContent['parts'][0]);
        $this->assertSame('get_weather', $lastContent['parts'][0]['functionResponse']['name']);
    }

    public function test_get_capabilities(): void
    {
        $transport = $this->createMockTransport([]);
        $provider = new GoogleProvider($transport, 'test-key');

        $capabilities = $provider->getCapabilities();

        $this->assertTrue($capabilities->supportsTools);
        $this->assertTrue($capabilities->supportsJsonMode);
        $this->assertTrue($capabilities->supportsStreaming);
        $this->assertTrue($capabilities->supportsVision);
        $this->assertTrue($capabilities->supportsSystemPrompt);
    }

    public function test_get_name(): void
    {
        $transport = $this->createMockTransport([]);
        $provider = new GoogleProvider($transport, 'test-key');

        $this->assertSame('google', $provider->getName());
    }

    public function test_custom_base_url(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());

        $provider = new GoogleProvider($transport, 'test-key', 'https://custom.api.com');
        $provider->generate(new GenerationRequest(
            model: 'gemini-pro',
            messages: [Message::user('Hi')],
        ));

        $this->assertStringStartsWith('https://custom.api.com', $transport->capturedUrl);
    }

    public function test_empty_response_handling(): void
    {
        $response = [
            'candidates' => [
                [
                    'content' => ['parts' => []],
                    'finishReason' => 'STOP',
                ],
            ],
        ];

        $transport = $this->createMockTransport($response);
        $provider = new GoogleProvider($transport, 'test-key');

        $result = $provider->generate(new GenerationRequest(
            model: 'gemini-pro',
            messages: [Message::user('Hi')],
        ));

        $this->assertNull($result->text);
        $this->assertEmpty($result->toolCalls);
    }

    public function test_multiple_text_parts(): void
    {
        $response = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Hello '],
                            ['text' => 'world!'],
                        ],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ];

        $transport = $this->createMockTransport($response);
        $provider = new GoogleProvider($transport, 'test-key');

        $result = $provider->generate(new GenerationRequest(
            model: 'gemini-pro',
            messages: [Message::user('Hi')],
        ));

        $this->assertSame('Hello world!', $result->text);
    }

    public function test_no_generation_config_when_empty(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());

        $provider = new GoogleProvider($transport, 'test-key');
        $provider->generate(new GenerationRequest(
            model: 'gemini-pro',
            messages: [Message::user('Hello')],
        ));

        $this->assertArrayNotHasKey('generationConfig', $transport->capturedBody);
    }

    public function test_system_message_in_messages(): void
    {
        $transport = $this->createMockTransport($this->createBasicResponse());

        $provider = new GoogleProvider($transport, 'test-key');
        $provider->generate(new GenerationRequest(
            model: 'gemini-pro',
            messages: [
                Message::system('Be helpful'),
                Message::user('Hello'),
            ],
        ));

        $this->assertArrayHasKey('systemInstruction', $transport->capturedBody);
        $this->assertSame('Be helpful', $transport->capturedBody['systemInstruction']['parts'][0]['text']);
        $this->assertCount(1, $transport->capturedBody['contents']);
    }
}
