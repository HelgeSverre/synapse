<?php

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createExecutor;
use function HelgeSverre\Synapse\createInMemoryTraceExporter;
use function HelgeSverre\Synapse\createParser;
use function HelgeSverre\Synapse\createTraceBridge;
use function HelgeSverre\Synapse\createTraceContext;

use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\State\Message;

$provider = new class implements LlmProviderInterface
{
    public function generate(GenerationRequest $request): GenerationResponse
    {
        return new GenerationResponse(
            text: 'Traced response',
            messages: [Message::assistant('Traced response')],
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
        return 'trace-demo';
    }
};

$executor = createExecutor([
    'llm' => $provider,
    'model' => 'trace-model',
    'prompt' => createChatPrompt()->addUserMessage('{{question}}', parseTemplate: true),
    'parser' => createParser('string'),
]);

$exporter = createInMemoryTraceExporter();
$traceBridge = createTraceBridge(
    exporter: $exporter,
    context: createTraceContext(['service' => 'trace-demo']),
);
$traceBridge->register($executor->getHooks());

$result = $executor->run(['question' => 'Can you trace this run?']);
echo 'Result: '.$result->getValue()."\n\n";

foreach ($exporter->getRecords() as $record) {
    echo sprintf(
        "[%s] %s success=%s duration=%.2fms\n",
        $record->traceId,
        $record->name,
        $record->success ? 'true' : 'false',
        $record->durationMs(),
    );
}
