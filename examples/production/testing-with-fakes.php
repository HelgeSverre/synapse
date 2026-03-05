<?php

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createExecutor;
use function HelgeSverre\Synapse\createParser;

use HelgeSverre\Synapse\Options\ExecutorOptions;
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\State\Message;

final readonly class FakeProvider implements LlmProviderInterface
{
    public function generate(GenerationRequest $request): GenerationResponse
    {
        return new GenerationResponse(
            text: 'deterministic-response',
            messages: [Message::assistant('deterministic-response')],
            toolCalls: [],
            model: $request->model,
            raw: ['fake' => true],
        );
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            supportsTools: false,
            supportsJsonMode: true,
            supportsStreaming: false,
            supportsVision: false,
            supportsSystemPrompt: true,
        );
    }

    public function getName(): string
    {
        return 'fake';
    }
}

$executor = createExecutor(new ExecutorOptions(
    llm: new FakeProvider,
    model: 'fake-model',
    prompt: createChatPrompt()->addUserMessage('{{input}}', parseTemplate: true),
    parser: createParser('string'),
));

$result = $executor->run(['input' => 'hello']);
assert($result->getValue() === 'deterministic-response');

echo "Fake test passed\n";
