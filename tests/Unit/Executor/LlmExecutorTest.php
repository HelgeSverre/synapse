<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Executor;

use HelgeSverre\Synapse\Executor\LlmExecutor;
use HelgeSverre\Synapse\Parser\StringParser;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\State\Message;
use PHPUnit\Framework\TestCase;

final class CapturingLlmProvider implements LlmProviderInterface
{
    public ?GenerationRequest $capturedRequest = null;

    public function generate(GenerationRequest $request): GenerationResponse
    {
        $this->capturedRequest = $request;

        return new GenerationResponse(
            text: 'ok',
            messages: [Message::assistant('ok')],
            toolCalls: [],
            model: $request->model,
        );
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities;
    }

    public function getName(): string
    {
        return 'capturing';
    }
}

final class LlmExecutorTest extends TestCase
{
    public function test_run_alias_executes_pipeline(): void
    {
        $provider = new CapturingLlmProvider;
        $executor = new LlmExecutor(
            provider: $provider,
            prompt: (new TextPrompt)->setContent('Hello {{name}}'),
            parser: new StringParser,
            model: 'test-model',
        );

        $result = $executor->run(['name' => 'world']);

        $this->assertSame('ok', $result->getValue());
        $this->assertNotNull($provider->capturedRequest);
        $this->assertSame('Hello world', $provider->capturedRequest->messages[0]->content);
    }

    public function test_execute_with_history_argument_prepends_messages(): void
    {
        $provider = new CapturingLlmProvider;
        $executor = new LlmExecutor(
            provider: $provider,
            prompt: (new TextPrompt)->setContent('Question: {{q}}'),
            parser: new StringParser,
            model: 'test-model',
        );

        $history = [Message::assistant('Earlier response')];
        $executor->execute(['q' => 'new'], $history);

        $this->assertCount(2, $provider->capturedRequest->messages);
        $this->assertSame('Earlier response', $provider->capturedRequest->messages[0]->content);
        $this->assertSame('Question: new', $provider->capturedRequest->messages[1]->content);
    }

    public function test_with_history_applies_default_history(): void
    {
        $provider = new CapturingLlmProvider;
        $executor = (new LlmExecutor(
            provider: $provider,
            prompt: (new TextPrompt)->setContent('Ping'),
            parser: new StringParser,
            model: 'test-model',
        ))->withHistory([Message::user('historic')]);

        $executor->run([]);

        $this->assertCount(2, $provider->capturedRequest->messages);
        $this->assertSame('historic', $provider->capturedRequest->messages[0]->content);
    }
}
