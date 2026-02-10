# Mistral Embeddings

## Setup

```php
$embeddings = useEmbeddings('mistral', [
    'apiKey' => getenv('MISTRAL_API_KEY'),
]);
```

## Options

| Option | Type | Default |
|--------|------|---------|
| `apiKey` | `string` | Required |
| `baseUrl` | `string` | `https://api.mistral.ai/v1` |
| `transport` | `TransportInterface` | Auto-discovered |

## Example

```php
$embeddings = useEmbeddings('mistral', ['apiKey' => getenv('MISTRAL_API_KEY')]);

$response = $embeddings->embed(
    'PHP is a server-side scripting language.',
    'mistral-embed',
);

$vector = $response->getEmbedding();
```
