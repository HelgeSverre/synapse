# Voyage Embeddings

## Setup

```php
$embeddings = useEmbeddings('voyage', [
    'apiKey' => getenv('VOYAGE_API_KEY'),
]);
```

## Options

| Option | Type | Default |
|--------|------|---------|
| `apiKey` | `string` | Required |
| `baseUrl` | `string` | `https://api.voyageai.com/v1` |
| `transport` | `TransportInterface` | Auto-discovered |

## Example

```php
$embeddings = useEmbeddings('voyage', ['apiKey' => getenv('VOYAGE_API_KEY')]);

$response = $embeddings->embed(
    'PHP is a server-side scripting language.',
    'voyage-3',
);

$vector = $response->getEmbedding();
```
