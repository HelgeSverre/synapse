# Cohere Embeddings

## Setup

```php
$embeddings = useEmbeddings('cohere', [
    'apiKey' => getenv('COHERE_API_KEY'),
]);
```

## Options

| Option | Type | Default |
|--------|------|---------|
| `apiKey` | `string` | Required |
| `baseUrl` | `string` | `https://api.cohere.com/v2` |
| `transport` | `TransportInterface` | Auto-discovered |

## Example

```php
$embeddings = useEmbeddings('cohere', ['apiKey' => getenv('COHERE_API_KEY')]);

$response = $embeddings->embed(
    'PHP is a server-side scripting language.',
    'embed-english-v3.0',
);

$vector = $response->getEmbedding();
```
