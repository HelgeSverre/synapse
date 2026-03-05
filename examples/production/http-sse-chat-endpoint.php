<?php

declare(strict_types=1);

require_once __DIR__.'/../../vendor/autoload.php';

use function HelgeSverre\Synapse\createChatPrompt;
use function HelgeSverre\Synapse\createExecutor;

use HelgeSverre\Synapse\Options\ExecutorOptions;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\TextDelta;

use function HelgeSverre\Synapse\useLlm;

$question = $_GET['q'] ?? null;
if (! is_string($question) || trim($question) === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing query param: q']);
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$llm = useLlm('openai', ['apiKey' => getenv('OPENAI_API_KEY'), 'model' => 'gpt-4o-mini']);
$executor = createExecutor(new ExecutorOptions(
    llm: $llm,
    prompt: createChatPrompt()
        ->addSystemMessage('You are concise and accurate.')
        ->addUserMessage('{{q}}', parseTemplate: true),
    stream: true,
));

foreach ($executor->stream(['q' => $question]) as $event) {
    if ($event instanceof TextDelta) {
        echo 'data: '.json_encode(['type' => 'token', 'text' => $event->text])."\n\n";
        @ob_flush();
        flush();
    }

    if ($event instanceof StreamCompleted) {
        echo 'data: '.json_encode(['type' => 'done', 'finishReason' => $event->finishReason])."\n\n";
        @ob_flush();
        flush();
    }
}
