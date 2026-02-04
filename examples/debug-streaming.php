<?php

declare(strict_types=1);

/**
 * Debug script for streaming providers
 *
 * Usage:
 *   php examples/debug-streaming.php mistral
 *   php examples/debug-streaming.php moonshot
 */

require_once __DIR__.'/../vendor/autoload.php';

use GuzzleHttp\Client;
use HelgeSverre\Synapse\Streaming\SseParser;

$provider = $argv[1] ?? 'mistral';

if ($provider === 'mistral') {
    $apiKey = getenv('MISTRAL_API_KEY');
    if (! $apiKey) {
        exit("MISTRAL_API_KEY not set\n");
    }
    $url = 'https://api.mistral.ai/v1/chat/completions';
    $model = 'mistral-small-latest';
    $headers = [
        'Authorization' => "Bearer {$apiKey}",
        'Content-Type' => 'application/json',
    ];
} elseif ($provider === 'moonshot') {
    $apiKey = getenv('MOONSHOT_API_KEY');
    if (! $apiKey) {
        exit("MOONSHOT_API_KEY not set\n");
    }
    $url = 'https://api.moonshot.ai/v1/chat/completions';
    $model = 'moonshot-v1-8k';
    $headers = [
        'Authorization' => "Bearer {$apiKey}",
        'Content-Type' => 'application/json',
    ];
} else {
    exit("Unknown provider: {$provider}. Use: mistral or moonshot\n");
}

$body = [
    'model' => $model,
    'messages' => [
        ['role' => 'user', 'content' => 'Say "hello world"'],
    ],
    'max_tokens' => 20,
    'stream' => true,
];

echo "=== Request ===\n";
echo "Provider: {$provider}\n";
echo "URL: {$url}\n";
echo 'Body: '.json_encode($body, JSON_PRETTY_PRINT)."\n\n";

$client = new Client(['timeout' => 60]);

try {
    $response = $client->request('POST', $url, [
        'headers' => $headers,
        'json' => $body,
        'stream' => true,
        'http_errors' => false,
    ]);
} catch (Throwable $e) {
    exit('Request failed: '.$e->getMessage()."\n");
}

echo "=== Response ===\n";
echo 'Status: '.$response->getStatusCode()."\n";
echo 'Content-Type: '.($response->getHeader('Content-Type')[0] ?? 'none')."\n\n";

if ($response->getStatusCode() >= 400) {
    echo "Error body:\n".$response->getBody()->getContents()."\n";
    exit(1);
}

$stream = $response->getBody();

echo "=== Raw Stream ===\n";
$rawContent = '';
$buffer = '';
$lineNum = 0;

while (! $stream->eof()) {
    $byte = $stream->read(1);
    if ($byte === '') {
        break;
    }

    $buffer .= $byte;
    $rawContent .= $byte;

    if ($byte === "\n") {
        $lineNum++;
        $displayLine = rtrim($buffer, "\r\n");
        echo "[{$lineNum}] ".substr($displayLine, 0, 120);
        if (strlen($displayLine) > 120) {
            echo '...';
        }
        echo "\n";
        $buffer = '';
    }
}

if ($buffer !== '') {
    $lineNum++;
    echo "[{$lineNum}] {$buffer}\n";
}

echo "\n=== SSE Parsing ===\n";
$lines = explode("\n", $rawContent);
$eventNum = 0;

foreach (SseParser::parse($lines) as $event) {
    $eventNum++;
    $eventType = $event['event'] ?? 'null';
    $data = $event['data'];

    echo "Event {$eventNum}:\n";
    echo "  Type: {$eventType}\n";
    echo '  Data: '.substr($data, 0, 200);
    if (strlen($data) > 200) {
        echo '...';
    }
    echo "\n";

    if ($data !== '[DONE]' && $data !== '') {
        $decoded = json_decode($data, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            echo '  JSON Error: '.json_last_error_msg()."\n";
        } else {
            $choice = $decoded['choices'][0] ?? null;
            if ($choice) {
                $delta = $choice['delta'] ?? [];
                $content = $delta['content'] ?? '(no content)';
                echo "  Content: {$content}\n";
            }
        }
    }
    echo "\n";
}

echo "=== Summary ===\n";
echo 'Total bytes: '.strlen($rawContent)."\n";
echo "Total lines: {$lineNum}\n";
echo "Total SSE events: {$eventNum}\n";
