<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/profilinator2000/CdpAdapterInterface.php';
require_once __DIR__.'/profilinator2000/FakeCdpAdapter.php';
require_once __DIR__.'/profilinator2000/RealCdpAdapter.php';
require_once __DIR__.'/profilinator2000/ReportWriter.php';
require_once __DIR__.'/profilinator2000/RunResult.php';
require_once __DIR__.'/profilinator2000/TaskPromptBuilder.php';
require_once __DIR__.'/profilinator2000/ToolCatalog.php';
require_once __DIR__.'/profilinator2000/SafeToolExecutor.php';
require_once __DIR__.'/profilinator2000/PerfAgentLoop.php';

use GuzzleHttp\Client;
use HelgeSverre\Synapse\Examples\Profilinator2000\FakeCdpAdapter;
use HelgeSverre\Synapse\Examples\Profilinator2000\PerfAgentLoop;
use HelgeSverre\Synapse\Examples\Profilinator2000\RealCdpAdapter;
use HelgeSverre\Synapse\Examples\Profilinator2000\ReportWriter;
use HelgeSverre\Synapse\Examples\Profilinator2000\SafeToolExecutor;
use HelgeSverre\Synapse\Examples\Profilinator2000\ToolCatalog;
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use HelgeSverre\Synapse\Streaming\StreamableProviderInterface;

use function HelgeSverre\Synapse\useLlm;

/**
 * Usage:
 * php examples/profilinator2000-cli.php --url="https://example.com" --test="calendar navigation"
 * php examples/profilinator2000-cli.php --provider=openai --model=gpt-4o-mini --adapter=fake --output-dir=./out
 */
$options = getopt('', [
    'help',
    'url:',
    'test:',
    'provider::',
    'model::',
    'adapter::',
    'output-dir::',
    'max-turns::',
    'api-key::',
]);

/**
 * @param  array<string, mixed>  $options
 */
$optionString = static function (array $options, string $key, string $default = ''): string {
    $value = $options[$key] ?? $default;

    if (is_array($value)) {
        $value = $value[0] ?? $default;
    }

    if (! is_string($value)) {
        return $default;
    }

    return $value;
};

if (isset($options['help']) || ! isset($options['url'], $options['test'])) {
    echo "Profilinator2000 (Synapse example)\n\n";
    echo "Required:\n";
    echo "  --url          URL to profile\n";
    echo "  --test         Human-readable performance objective\n\n";
    echo "Optional:\n";
    echo "  --provider     openai|anthropic|google|mistral|xai|groq|moonshot (default: openai)\n";
    echo "  --model        model identifier (default: gpt-4o-mini)\n";
    echo "  --adapter      fake|real (default: fake)\n";
    echo "  --output-dir   where PERFORMANCE-REPORT.md is written (default: .)\n";
    echo "  --max-turns    outer loop max turns (default: 8)\n";
    echo "  --api-key      API key override (otherwise from environment)\n";
    exit(0);
}

$providerName = strtolower($optionString($options, 'provider', 'openai'));
$model = $optionString($options, 'model', 'gpt-4o-mini');
$adapterName = strtolower($optionString($options, 'adapter', 'fake'));
$outputDir = $optionString($options, 'output-dir', '.');
$maxTurns = max(1, (int) $optionString($options, 'max-turns', '8'));
$url = $optionString($options, 'url');
$testDescription = $optionString($options, 'test');

$envKeyName = match ($providerName) {
    'anthropic' => 'ANTHROPIC_API_KEY',
    'google' => 'GOOGLE_API_KEY',
    'mistral' => 'MISTRAL_API_KEY',
    'xai' => 'XAI_API_KEY',
    'groq' => 'GROQ_API_KEY',
    'moonshot' => 'MOONSHOT_API_KEY',
    default => 'OPENAI_API_KEY',
};

$apiKey = $optionString($options, 'api-key', '');
if ($apiKey === '') {
    $envApiKey = getenv($envKeyName);
    $apiKey = is_string($envApiKey) ? $envApiKey : '';
}
if ($apiKey === '') {
    fwrite(STDERR, "Missing API key. Set {$envKeyName} or pass --api-key.\n");
    exit(1);
}

if (! class_exists(Client::class)) {
    fwrite(STDERR, "guzzlehttp/guzzle is required for streaming examples.\n");
    exit(1);
}

$transport = new GuzzleStreamTransport(new Client(['timeout' => 60]));

$provider = useLlm("{$providerName}.{$model}", [
    'apiKey' => $apiKey,
    'transport' => $transport,
]);

if (! $provider instanceof StreamableProviderInterface) {
    fwrite(STDERR, "Provider {$providerName} does not support streaming in this setup.\n");
    exit(1);
}

$cdp = $adapterName === 'real' ? new RealCdpAdapter : new FakeCdpAdapter;
$writer = new ReportWriter;
$catalog = new ToolCatalog($cdp, $writer, $outputDir);
$tools = new SafeToolExecutor($catalog->createToolRegistry(), 8000);
$loop = new PerfAgentLoop($provider, $tools, $writer, $model);

echo "Running Profilinator2000 on {$url}\n";
echo "Objective: {$testDescription}\n";
echo "Provider: {$providerName} | Model: {$model} | Adapter: {$adapterName}\n\n";

try {
    $result = $loop->run($url, $testDescription, $maxTurns);
} catch (\Throwable $e) {
    fwrite(STDERR, "Profilinator2000 failed: {$e->getMessage()}\n");
    exit(1);
}

if (! $result->success) {
    fwrite(STDERR, "Failed to save report within {$result->turns} turns.\n");
    exit(1);
}

echo "Report saved to: {$result->reportPath}\n";
