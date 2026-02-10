# HTTP Transport

Synapse uses a transport layer to make HTTP requests to LLM APIs. All transports implement `TransportInterface`.

## Transport Types

| Transport | Use Case |
|-----------|----------|
| [Psr18Transport](/http/psr18) | Standard buffered HTTP requests |
| [GuzzleStreamTransport](/http/guzzle-stream) | Streaming responses |

## Auto-Discovery

Synapse auto-discovers an HTTP transport if Guzzle or Symfony HTTP Client is installed:

```php
// Just works — no transport configuration needed
$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => '...']);
```

## Manual Configuration

```php
use HelgeSverre\Synapse\Factory;

$client = new \GuzzleHttp\Client(['timeout' => 30]);
$factory = new \GuzzleHttp\Psr7\HttpFactory();

Factory::setDefaultTransport(
    Factory::createTransport($client, $factory, $factory)
);
```
