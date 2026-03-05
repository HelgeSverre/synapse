<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit;

use Generator;
use HelgeSverre\Synapse\Executor\LlmExecutor;
use HelgeSverre\Synapse\Executor\LlmExecutorWithFunctions;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutor;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutorWithFunctions;
use HelgeSverre\Synapse\Factory;
use HelgeSverre\Synapse\Options\CallableExecutorOptions;
use HelgeSverre\Synapse\Options\ExecutorOptions;
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\StreamableProviderInterface;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\StreamContext;
use PHPUnit\Framework\TestCase;

final class NonStreamProvider implements LlmProviderInterface
{
    public function generate(GenerationRequest $request): GenerationResponse
    {
        return new GenerationResponse('ok', [Message::assistant('ok')], [], $request->model);
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities;
    }

    public function getName(): string
    {
        return 'non-stream';
    }
}

final class SimpleStreamProvider implements StreamableProviderInterface
{
    public function generate(GenerationRequest $request): GenerationResponse
    {
        return new GenerationResponse('ok', [Message::assistant('ok')], [], $request->model);
    }

    public function stream(GenerationRequest $request, ?StreamContext $ctx = null): Generator
    {
        yield new StreamCompleted('stop');
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(supportsStreaming: true);
    }

    public function getName(): string
    {
        return 'stream';
    }
}

final class FactoryUnifiedApiTest extends TestCase
{
    public function test_create_executor_returns_llm_executor(): void
    {
        $executor = Factory::createExecutor(new ExecutorOptions(
            llm: new NonStreamProvider,
            model: 'test-model',
            prompt: Factory::createTextPrompt()->setContent('hello'),
        ));

        $this->assertInstanceOf(LlmExecutor::class, $executor);
    }

    public function test_create_executor_returns_llm_executor_with_functions(): void
    {
        $tools = Factory::createToolRegistry([
            new CallableExecutorOptions(
                name: 'noop',
                description: 'No-op',
                handler: fn (): string => 'ok',
            ),
        ]);

        $executor = Factory::createExecutor(new ExecutorOptions(
            llm: new NonStreamProvider,
            model: 'test-model',
            prompt: Factory::createTextPrompt()->setContent('hello'),
            tools: $tools,
        ));

        $this->assertInstanceOf(LlmExecutorWithFunctions::class, $executor);
    }

    public function test_create_executor_returns_streaming_executor(): void
    {
        $executor = Factory::createExecutor(new ExecutorOptions(
            llm: new SimpleStreamProvider,
            model: 'test-model',
            prompt: Factory::createTextPrompt()->setContent('hello'),
            stream: true,
        ));

        $this->assertInstanceOf(StreamingLlmExecutor::class, $executor);
    }

    public function test_create_executor_returns_streaming_executor_with_functions(): void
    {
        $tools = Factory::createToolRegistry([
            [
                'name' => 'noop',
                'description' => 'No-op',
                'handler' => fn (): string => 'ok',
            ],
        ]);

        $executor = Factory::createExecutor(new ExecutorOptions(
            llm: new SimpleStreamProvider,
            model: 'test-model',
            prompt: Factory::createTextPrompt()->setContent('hello'),
            tools: $tools,
            stream: true,
        ));

        $this->assertInstanceOf(StreamingLlmExecutorWithFunctions::class, $executor);
    }
}
