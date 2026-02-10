# OpenAI

## Setup

```php
$llm = useLlm('openai.gpt-4o-mini', [
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

Use any OpenAI model by appending it to the prefix:

```php
$llm = useLlm('openai.gpt-4o', [...]);
$llm = useLlm('openai.gpt-4o-mini', [...]);
$llm = useLlm('openai.gpt-4-turbo', [...]);
$llm = useLlm('openai.o1-mini', [...]);
```

## OpenAI-Compatible APIs

Override `baseUrl` to use OpenAI-compatible APIs (Azure OpenAI, local models, proxies):

```php
$llm = useLlm('openai.my-model', [
    'apiKey' => getenv('API_KEY'),
    'baseUrl' => 'https://my-proxy.example.com/v1',
]);
```

## Streaming

```php
use HelgeSverre\Synapse\Provider\Http\GuzzleStreamTransport;
use GuzzleHttp\Client;

$transport = new GuzzleStreamTransport(new Client(['timeout' => 60]));

$llm = useLlm('openai.gpt-4o-mini', [
    'apiKey' => getenv('OPENAI_API_KEY'),
    'transport' => $transport,
]);
```
