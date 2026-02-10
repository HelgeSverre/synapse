# Jina Embeddings

## Setup

```php
$embeddings = useEmbeddings('jina', [
    'apiKey' => getenv('JINA_API_KEY'),
]);
```

## Options

| Option | Type | Default |
|--------|------|---------|
| `apiKey` | `string` | Required |
| `baseUrl` | `string` | `https://api.jina.ai/v1` |
| `transport` | `TransportInterface` | Auto-discovered |

## Example

```php
$embeddings = useEmbeddings('jina', ['apiKey' => getenv('JINA_API_KEY')]);

$response = $embeddings->embed(
    'PHP is a server-side scripting language.',
    'jina-embeddings-v3',
);

$vector = $response->getEmbedding();
```
