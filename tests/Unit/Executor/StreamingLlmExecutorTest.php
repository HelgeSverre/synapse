<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Executor;

use Generator;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutor;
use HelgeSverre\Synapse\Executor\StreamingResult;
use HelgeSverre\Synapse\Hooks\Events\OnStreamChunk;
use HelgeSverre\Synapse\Hooks\Events\OnStreamEnd;
use HelgeSverre\Synapse\Hooks\Events\OnStreamStart;
use HelgeSverre\Synapse\Hooks\Events\OnStreamSuccess;
use HelgeSverre\Synapse\Hooks\HookDispatcher;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\Provider\Response\UsageInfo;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\StreamableProviderInterface;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\StreamContext;
use HelgeSverre\Synapse\Streaming\StreamEvent;
use HelgeSverre\Synapse\Streaming\TextDelta;
use PHPUnit\Framework\TestCase;

final class MockStreamableProvider implements StreamableProviderInterface
{
    /** @var list<StreamEvent> */
    public array $events = [];

    public ?GenerationRequest $capturedRequest = null;

    public function generate(GenerationRequest $request): GenerationResponse
    {
        return new GenerationResponse(text: 'Hello', messages: [Message::assistant('Hello')]);
    }

    public function stream(GenerationRequest $request, ?StreamContext $ctx = null): Generator
    {
        $this->capturedRequest = $request;

        foreach ($this->events as $event) {
            if ($ctx?->shouldCancel()) {
                return;
            }
            yield $event;
        }
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(supportsStreaming: true);
    }

    public function getName(): string
    {
        return 'mock';
    }
}

final class StreamingLlmExecutorTest extends TestCase
{
    private function createPrompt(string $content): TextPrompt
    {
        return (new TextPrompt)->setContent($content);
    }

    public function test_stream_yields_events_from_provider(): void
    {
        $provider = new MockStreamableProvider;
        $provider->events = [
            new TextDelta('Hello'),
            new TextDelta(' world'),
            new StreamCompleted('stop'),
        ];

        $prompt = $this->createPrompt('Say hi');
        $executor = new StreamingLlmExecutor($provider, $prompt, 'gpt-4');

        $events = iterator_to_array($executor->stream([]));

        $this->assertCount(3, $events);
        $this->assertInstanceOf(TextDelta::class, $events[0]);
        $this->assertSame('Hello', $events[0]->text);
        $this->assertInstanceOf(TextDelta::class, $events[1]);
        $this->assertSame(' world', $events[1]->text);
        $this->assertInstanceOf(StreamCompleted::class, $events[2]);
    }

    public function test_stream_passes_model_to_request(): void
    {
        $provider = new MockStreamableProvider;
        $provider->events = [new StreamCompleted('stop')];

        $prompt = $this->createPrompt('Say hi');
        $executor = new StreamingLlmExecutor($provider, $prompt, 'gpt-4-turbo');

        iterator_to_array($executor->stream([]));

        $this->assertSame('gpt-4-turbo', $provider->capturedRequest->model);
    }

    public function test_stream_passes_temperature_and_max_tokens(): void
    {
        $provider = new MockStreamableProvider;
        $provider->events = [new StreamCompleted('stop')];

        $prompt = $this->createPrompt('Say hi');
        $executor = new StreamingLlmExecutor(
            $provider,
            $prompt,
            'gpt-4',
            temperature: 0.7,
            maxTokens: 100,
        );

        iterator_to_array($executor->stream([]));

        $this->assertSame(0.7, $provider->capturedRequest->temperature);
        $this->assertSame(100, $provider->capturedRequest->maxTokens);
    }

    public function test_stream_renders_prompt_with_input(): void
    {
        $provider = new MockStreamableProvider;
        $provider->events = [new StreamCompleted('stop')];

        $prompt = $this->createPrompt('Hello {{name}}');
        $executor = new StreamingLlmExecutor($provider, $prompt, 'gpt-4');

        iterator_to_array($executor->stream(['name' => 'World']));

        $this->assertSame('Hello World', $provider->capturedRequest->messages[0]->content);
    }

    public function test_stream_and_collect_returns_full_text(): void
    {
        $provider = new MockStreamableProvider;
        $provider->events = [
            new TextDelta('Hello'),
            new TextDelta(' '),
            new TextDelta('world'),
            new StreamCompleted('stop', new UsageInfo(10, 5, 15)),
        ];

        $prompt = $this->createPrompt('Say hi');
        $executor = new StreamingLlmExecutor($provider, $prompt, 'gpt-4');

        $result = $executor->streamAndCollect([]);

        $this->assertInstanceOf(StreamingResult::class, $result);
        $this->assertSame('Hello world', $result->text);
        $this->assertSame('stop', $result->finishReason);
        $this->assertNotNull($result->usage);
        $this->assertSame(10, $result->usage->inputTokens);
    }

    public function test_stream_updates_state_with_assistant_message(): void
    {
        $provider = new MockStreamableProvider;
        $provider->events = [
            new TextDelta('Response text'),
            new StreamCompleted('stop'),
        ];

        $prompt = $this->createPrompt('Say hi');
        $executor = new StreamingLlmExecutor($provider, $prompt, 'gpt-4');

        iterator_to_array($executor->stream([]));

        $state = $executor->getState();
        $this->assertCount(1, $state->messages);
        $this->assertSame('Response text', $state->messages[0]->content);
    }

    public function test_stream_dispatches_hooks(): void
    {
        $provider = new MockStreamableProvider;
        $provider->events = [
            new TextDelta('Hi'),
            new StreamCompleted('stop'),
        ];

        $prompt = $this->createPrompt('Say hi');
        $hooks = new HookDispatcher;

        $receivedEvents = [];
        $hooks->addListener(OnStreamStart::class, function ($e) use (&$receivedEvents): void {
            $receivedEvents[] = 'start';
        });
        $hooks->addListener(OnStreamChunk::class, function ($e) use (&$receivedEvents): void {
            $receivedEvents[] = 'chunk:'.get_class($e->event);
        });
        $hooks->addListener(OnStreamEnd::class, function ($e) use (&$receivedEvents): void {
            $receivedEvents[] = 'end';
        });
        $hooks->addListener(OnStreamSuccess::class, function ($e) use (&$receivedEvents): void {
            $receivedEvents[] = 'success';
        });

        $executor = new StreamingLlmExecutor($provider, $prompt, 'gpt-4', hooks: $hooks);

        iterator_to_array($executor->stream([]));

        $this->assertContains('start', $receivedEvents);
        $this->assertContains('chunk:'.TextDelta::class, $receivedEvents);
        $this->assertContains('chunk:'.StreamCompleted::class, $receivedEvents);
        $this->assertContains('end', $receivedEvents);
        $this->assertContains('success', $receivedEvents);
    }

    public function test_stream_can_be_cancelled_via_context(): void
    {
        $provider = new MockStreamableProvider;
        $provider->events = [
            new TextDelta('One'),
            new TextDelta('Two'),
            new TextDelta('Three'),
            new StreamCompleted('stop'),
        ];

        $prompt = $this->createPrompt('Count');
        $executor = new StreamingLlmExecutor($provider, $prompt, 'gpt-4');

        $count = 0;
        $ctx = new StreamContext(isCancelled: function () use (&$count) {
            return $count >= 2;
        });

        $events = [];
        foreach ($executor->stream([], $ctx) as $event) {
            $events[] = $event;
            if ($event instanceof TextDelta) {
                $count++;
            }
        }

        $textDeltas = array_filter($events, fn ($e) => $e instanceof TextDelta);
        $this->assertLessThanOrEqual(2, count($textDeltas));
    }

    public function test_stream_throws_and_dispatches_error_hook(): void
    {
        $provider = new class implements StreamableProviderInterface
        {
            public function generate(GenerationRequest $request): GenerationResponse
            {
                throw new \RuntimeException('fail');
            }

            public function stream(GenerationRequest $request, ?StreamContext $ctx = null): Generator
            {
                throw new \RuntimeException('Stream failed');
                yield; // @phpstan-ignore-line
            }

            public function getCapabilities(): ProviderCapabilities
            {
                return new ProviderCapabilities(supportsStreaming: true);
            }

            public function getName(): string
            {
                return 'failing';
            }
        };

        $prompt = $this->createPrompt('Hi');
        $hooks = new HookDispatcher;

        $errorReceived = false;
        $hooks->addListener(\HelgeSverre\Synapse\Hooks\Events\OnError::class, function ($e) use (&$errorReceived): void {
            $errorReceived = true;
        });

        $executor = new StreamingLlmExecutor($provider, $prompt, 'gpt-4', hooks: $hooks);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stream failed');

        try {
            iterator_to_array($executor->stream([]));
        } finally {
            $this->assertTrue($errorReceived);
        }
    }

    public function test_get_provider_returns_provider(): void
    {
        $provider = new MockStreamableProvider;
        $prompt = $this->createPrompt('Hi');
        $executor = new StreamingLlmExecutor($provider, $prompt, 'gpt-4');

        $this->assertSame($provider, $executor->getProvider());
    }

    public function test_get_prompt_returns_prompt(): void
    {
        $provider = new MockStreamableProvider;
        $prompt = $this->createPrompt('Hi');
        $executor = new StreamingLlmExecutor($provider, $prompt, 'gpt-4');

        $this->assertSame($prompt, $executor->getPrompt());
    }

    public function test_get_model_returns_model(): void
    {
        $provider = new MockStreamableProvider;
        $prompt = $this->createPrompt('Hi');
        $executor = new StreamingLlmExecutor($provider, $prompt, 'gpt-4');

        $this->assertSame('gpt-4', $executor->getModel());
    }

    public function test_with_state_returns_clone_with_new_state(): void
    {
        $provider = new MockStreamableProvider;
        $prompt = $this->createPrompt('Hi');
        $executor = new StreamingLlmExecutor($provider, $prompt, 'gpt-4');

        $newState = $executor->getState()->withMessage(Message::user('test'));
        $newExecutor = $executor->withState($newState);

        $this->assertNotSame($executor, $newExecutor);
        $this->assertCount(1, $newExecutor->getState()->messages);
        $this->assertCount(0, $executor->getState()->messages);
    }

    public function test_on_adds_listener(): void
    {
        $provider = new MockStreamableProvider;
        $provider->events = [new TextDelta('Hi'), new StreamCompleted('stop')];
        $prompt = $this->createPrompt('Hi');
        $executor = new StreamingLlmExecutor($provider, $prompt, 'gpt-4');

        $received = [];
        $executor->on(OnStreamChunk::class, function ($e) use (&$received): void {
            $received[] = $e;
        });

        iterator_to_array($executor->stream([]));

        $this->assertCount(2, $received);
    }
}
