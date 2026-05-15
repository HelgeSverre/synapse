# Ollama

[Ollama](https://ollama.com) runs open-weights models locally and exposes an OpenAI-compatible HTTP API at `http://localhost:11434/v1`. Synapse talks to that endpoint, so you can use models like `gemma4`, `qwen3`, or `deepseek-r1` with the same `useLlm()` factory — no API key required.

## Prerequisites

1. Install Ollama from [ollama.com/download](https://ollama.com/download).
2. Start the server (one-time; it runs as a background service on macOS/Linux):
   ```bash
   ollama serve
   ```
3. Pull a model:
   ```bash
   ollama pull gemma4:latest
   ```

## Setup

```php
use function HelgeSverre\Synapse\useLlm;

$llm = useLlm('ollama', [
    'model'   => 'gemma4:latest',
    // baseUrl defaults to http://localhost:11434/v1
    // apiKey is optional — only needed if you front Ollama with a proxy
]);
```

## Options

| Option      | Type                 | Default                       |
| ----------- | -------------------- | ----------------------------- |
| `model`     | `string`             | Required (or set via executor)|
| `baseUrl`   | `string`             | `http://localhost:11434/v1`   |
| `apiKey`    | `?string`            | `null` — only sent if non-empty |
| `transport` | `TransportInterface` | Auto-discovered               |

## Usage

```php
use function HelgeSverre\Synapse\{useLlm, createExecutor, createChatPrompt, createParser};

$llm = useLlm('ollama', ['model' => 'gemma4:latest']);

$executor = createExecutor([
    'llm'    => $llm,
    'prompt' => createChatPrompt()
        ->addSystemMessage('You are helpful.')
        ->addUserMessage('{{question}}', parseTemplate: true),
    'parser' => createParser('string'),
]);

$answer = $executor->run(['question' => 'Briefly: what is Ollama?']);
```

## Tool calling

Ollama's OpenAI-compatible endpoint forwards `tools` and `tool_choice` to models that support function calling (Gemma 3+, Qwen 3+, Llama 3.1+, etc.). The provider parses tool calls back into Synapse's `ToolCall` shape, so the standard `createLlmExecutorWithFunctions()` flow and multi-turn ReAct loops work unchanged.

See [examples/ollama-react-agent.php](https://github.com/HelgeSverre/synapse/blob/main/examples/ollama-react-agent.php) for a complete manual ReAct loop using `gemma4:latest`.

## Streaming

Streaming follows the same SSE format as OpenAI:

```php
use HelgeSverre\Synapse\Factory;
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;

Factory::setDefaultTransport(new GuzzleStreamTransport(/* ... */));

$llm = useLlm('ollama', ['model' => 'gemma4:latest']);
```

See [Stream Transport](/streaming/transport) for transport setup.

## Capabilities

| Capability         | Supported |
| ------------------ | --------- |
| Tools              | ✅ (model-dependent) |
| Streaming          | ✅        |
| JSON / structured  | ✅ via `response_format` |
| Vision             | ✅ (model-dependent, e.g. `llava`, `gemma3` vision variants) |
| System prompts     | ✅        |

::: tip Remote Ollama
To target an Ollama server on another machine, set `baseUrl`:

```php
$llm = useLlm('ollama', [
    'baseUrl' => 'http://gpu-box.local:11434/v1',
    'model'   => 'qwen3.6:latest',
]);
```
:::
