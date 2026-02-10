# OpenAI Embeddings

## Setup

```php
$embeddings = useEmbeddings('openai', [
    'apiKey' => getenv('OPENAI_API_KEY'),
]);
```

## Options

| Option | Type | Default |
|--------|------|---------|
| `apiKey` | `string` | Required |
| `baseUrl` | `string` | `https://api.openai.com/v1` |
| `transport` | `TransportInterface` | Auto-discovered |

## Models

- `text-embedding-3-small` (1536 dimensions)
- `text-embedding-3-large` (3072 dimensions)
- `text-embedding-ada-002` (1536 dimensions, legacy)

## Example

```php
$embeddings = useEmbeddings('openai', ['apiKey' => getenv('OPENAI_API_KEY')]);

$response = $embeddings->embed(
    'PHP is a server-side scripting language.',
    'text-embedding-3-small',
);

$vector = $response->getEmbedding(); // float[1536]
```
