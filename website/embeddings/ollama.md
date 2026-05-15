# Ollama Embeddings

Run embedding models locally via [Ollama](https://ollama.com). Synapse talks to Ollama's OpenAI-compatible `/v1/embeddings` endpoint — no API key needed.

## Prerequisites

```bash
ollama serve                          # one-time / background service
ollama pull granite-embedding:latest  # or nomic-embed-text, mxbai-embed-large, embeddinggemma, ...
```

## Setup

```php
use function HelgeSverre\Synapse\useEmbeddings;

$embeddings = useEmbeddings('ollama');
// baseUrl defaults to http://localhost:11434/v1
```

## Options

| Option      | Type                 | Default                       |
| ----------- | -------------------- | ----------------------------- |
| `baseUrl`   | `string`             | `http://localhost:11434/v1`   |
| `apiKey`    | `?string`            | `null` (only sent if non-empty) |
| `transport` | `TransportInterface` | Auto-discovered               |

## Models

Any Ollama embedding model works. Common picks:

| Tag                          | Dimensions | Notes                                |
| ---------------------------- | ---------- | ------------------------------------ |
| `granite-embedding:latest`   | 384        | Small, fast, English-only baseline   |
| `nomic-embed-text`           | 768        | Long-context (~8k tokens)            |
| `mxbai-embed-large`          | 1024       | Strong general-purpose               |
| `embeddinggemma:latest`      | 768        | Gemma-family multilingual            |

Check `ollama list` to see which models you have locally.

## Example

```php
$embeddings = useEmbeddings('ollama');

$response = $embeddings->embed(
    ['The quick brown fox', 'PHP is a server-side language'],
    'granite-embedding:latest',
);

$response->embeddings;     // array<array<float>> — one vector per input
$response->getEmbedding(); // float[] — the first vector
$response->totalTokens;    // ?int — when Ollama reports usage
```

## Remote Ollama

Point at a different host by overriding `baseUrl`:

```php
$embeddings = useEmbeddings('ollama', [
    'baseUrl' => 'http://gpu-box.local:11434/v1',
]);
```
