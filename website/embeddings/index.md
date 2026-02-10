# Embeddings

Synapse provides a unified interface for generating text embeddings from multiple providers.

## The useEmbeddings() Factory

```php
use function HelgeSverre\Synapse\useEmbeddings;

$embeddings = useEmbeddings('openai', ['apiKey' => getenv('OPENAI_API_KEY')]);

$response = $embeddings->embed('The quick brown fox', 'text-embedding-3-small');

$vector = $response->getEmbedding();     // float[] — first embedding
$model = $response->model;               // string — model used
$tokens = $response->totalTokens;        // ?int — total tokens used
$all = $response->embeddings;            // array<array<float>> — all vectors
```

## Supported Providers

| Provider | Factory String | Default Base URL |
|----------|---------------|-----------------|
| [OpenAI](/embeddings/openai) | `'openai'` | `https://api.openai.com/v1` |
| [Mistral](/embeddings/mistral) | `'mistral'` | `https://api.mistral.ai/v1` |
| [Jina](/embeddings/jina) | `'jina'` | `https://api.jina.ai/v1` |
| [Cohere](/embeddings/cohere) | `'cohere'` | `https://api.cohere.com/v2` |
| [Voyage](/embeddings/voyage) | `'voyage'` | `https://api.voyageai.com/v1` |

## EmbeddingProviderInterface

```php
interface EmbeddingProviderInterface
{
    public function embed(string $text, string $model): EmbeddingResponse;
}
```

## EmbeddingResponse

```php
// Method
$response->getEmbedding();   // float[] — first embedding vector

// Public properties
$response->embeddings;       // array<array<float>> — all embedding vectors
$response->model;            // string — model used
$response->totalTokens;      // ?int — total tokens used
$response->raw;              // array — full raw API response
```
