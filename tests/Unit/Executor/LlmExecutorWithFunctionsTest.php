<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Executor;

use HelgeSverre\Synapse\Executor\LlmExecutorWithFunctions;
use HelgeSverre\Synapse\Executor\ToolCatalogResolver;
use HelgeSverre\Synapse\Executor\ToolExecutorInterface;
use HelgeSverre\Synapse\Executor\ToolResult;
use HelgeSverre\Synapse\Parser\StringParser;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Request\ToolCall;
use HelgeSverre\Synapse\Provider\Request\ToolDefinition;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\State\ConversationState;
use HelgeSverre\Synapse\State\Message;
use PHPUnit\Framework\TestCase;

final class SequenceProvider implements LlmProviderInterface
{
    /** @var list<GenerationResponse> */
    public array $responses = [];

    /** @var list<GenerationRequest> */
    public array $requests = [];

    public function generate(GenerationRequest $request): GenerationResponse
    {
        $this->requests[] = $request;

        $response = array_shift($this->responses);
        if (! $response instanceof GenerationResponse) {
            throw new \RuntimeException('No queued response in SequenceProvider');
        }

        return $response;
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(supportsTools: true);
    }

    public function getName(): string
    {
        return 'sequence-provider';
    }
}

final class RecordingToolExecutor implements ToolExecutorInterface
{
    /** @var list<array{name: string, input: array<string, mixed>, state: ConversationState|null}> */
    public array $calls = [];

    /** @var list<ToolDefinition> */
    public array $definitions;

    /** @param list<ToolDefinition>|null $definitions */
    public function __construct(?array $definitions = null)
    {
        $this->definitions = $definitions ?? [new ToolDefinition('calc', 'Calculator')];
    }

    public function getToolDefinitions(): array
    {
        return $this->definitions;
    }

    public function callFunctionResult(string $name, array $input, ?ConversationState $state = null): ToolResult
    {
        $this->calls[] = [
            'name' => $name,
            'input' => $input,
            'state' => $state,
        ];

        return ToolResult::success(['value' => 42]);
    }
}

final class LlmExecutorWithFunctionsTest extends TestCase
{
    public function test_executes_tool_loop_and_returns_final_parsed_value(): void
    {
        $provider = new SequenceProvider;
        $toolCall = new ToolCall('call_1', 'calc', ['expression' => '6*7']);
        $provider->responses = [
            new GenerationResponse(
                text: 'Using tool...',
                messages: [Message::assistant('Using tool...', [$toolCall])],
                toolCalls: [$toolCall],
                model: 'test-model',
                finishReason: 'tool_calls',
            ),
            new GenerationResponse(
                text: 'done',
                messages: [Message::assistant('done')],
                toolCalls: [],
                model: 'test-model',
                finishReason: 'stop',
            ),
        ];

        $tools = new RecordingToolExecutor;
        $executor = new LlmExecutorWithFunctions(
            provider: $provider,
            prompt: (new TextPrompt)->setContent('Q: {{question}}'),
            parser: new StringParser,
            model: 'test-model',
            tools: $tools,
        );

        $result = $executor->execute(['question' => 'what is 6*7?']);

        $this->assertSame('done', $result->getValue());
        $this->assertCount(2, $provider->requests);
        $this->assertCount(1, $tools->calls);

        $secondRequest = $provider->requests[1];
        $toolMessages = array_values(array_filter($secondRequest->messages, fn (Message $m) => $m->role->value === 'tool'));
        $this->assertCount(1, $toolMessages);

        $toolPayload = json_decode($toolMessages[0]->content, true);
        $this->assertTrue($toolPayload['success']);
        $this->assertSame(['value' => 42], $toolPayload['result']);
        $this->assertSame([], $toolPayload['errors']);
    }

    public function test_passes_state_to_tool_executor(): void
    {
        $provider = new SequenceProvider;
        $toolCall = new ToolCall('call_2', 'calc', ['expression' => '1+1']);
        $provider->responses = [
            new GenerationResponse(
                text: '',
                messages: [Message::assistant('', [$toolCall])],
                toolCalls: [$toolCall],
                model: 'test-model',
                finishReason: 'tool_calls',
            ),
            new GenerationResponse(
                text: 'ok',
                messages: [Message::assistant('ok')],
                toolCalls: [],
                model: 'test-model',
                finishReason: 'stop',
            ),
        ];

        $tools = new RecordingToolExecutor;
        $state = (new ConversationState)->withAttribute('session_id', 'abc-123');

        $executor = new LlmExecutorWithFunctions(
            provider: $provider,
            prompt: (new TextPrompt)->setContent('{{question}}'),
            parser: new StringParser,
            model: 'test-model',
            tools: $tools,
            state: $state,
        );

        $executor->execute(['question' => 'compute']);

        $this->assertCount(1, $tools->calls);
        $receivedState = $tools->calls[0]['state'];
        $this->assertInstanceOf(ConversationState::class, $receivedState);
        $this->assertSame('abc-123', $receivedState->getAttribute('session_id'));
    }

    public function test_throws_when_max_iterations_exceeded(): void
    {
        $provider = new SequenceProvider;
        $toolCall = new ToolCall('call_loop', 'calc', []);
        $provider->responses = [
            new GenerationResponse(
                text: '',
                messages: [Message::assistant('', [$toolCall])],
                toolCalls: [$toolCall],
                model: 'test-model',
                finishReason: 'tool_calls',
            ),
            new GenerationResponse(
                text: '',
                messages: [Message::assistant('', [$toolCall])],
                toolCalls: [$toolCall],
                model: 'test-model',
                finishReason: 'tool_calls',
            ),
        ];

        $tools = new RecordingToolExecutor;
        $executor = new LlmExecutorWithFunctions(
            provider: $provider,
            prompt: (new TextPrompt)->setContent('{{question}}'),
            parser: new StringParser,
            model: 'test-model',
            tools: $tools,
            maxIterations: 1,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Max tool iterations (1) exceeded');

        $executor->execute(['question' => 'loop']);
    }

    public function test_uses_tool_catalog_resolver_for_request_tools(): void
    {
        $provider = new SequenceProvider;
        $provider->responses = [
            new GenerationResponse(
                text: 'done',
                messages: [Message::assistant('done')],
                toolCalls: [],
                model: 'test-model',
                finishReason: 'stop',
            ),
        ];

        $tools = new RecordingToolExecutor([new ToolDefinition('calc', 'Calculator')]);
        $resolver = new class implements ToolCatalogResolver
        {
            public function resolve(array $input, ConversationState $state, int $iteration, ToolExecutorInterface $tools): array
            {
                return [];
            }
        };

        $executor = new LlmExecutorWithFunctions(
            provider: $provider,
            prompt: (new TextPrompt)->setContent('Q: {{question}}'),
            parser: new StringParser,
            model: 'test-model',
            tools: $tools,
            toolCatalogResolver: $resolver,
        );

        $executor->execute(['question' => 'hello']);

        $this->assertCount(1, $provider->requests);
        $this->assertSame([], $provider->requests[0]->tools);
    }
}
