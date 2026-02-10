# PSR-18 Transport

The default transport. Wraps a PSR-18 HTTP client, PSR-17 request factory, and PSR-17 stream factory.

## Setup

```php
use HelgeSverre\Synapse\Factory;

// With Guzzle
$client = new \GuzzleHttp\Client(['timeout' => 30]);
$factory = new \GuzzleHttp\Psr7\HttpFactory();
$transport = Factory::createTransport($client, $factory, $factory);

// With Symfony
$client = new \Symfony\Component\HttpClient\Psr18Client();
$transport = Factory::createTransport($client, $client, $client);
```

## Constructor

```php
use HelgeSverre\Synapse\Provider\Http\Psr18Transport;

$transport = new Psr18Transport(
    client: $psrClient,          // Psr\Http\Client\ClientInterface
    requestFactory: $reqFactory, // Psr\Http\Message\RequestFactoryInterface
    streamFactory: $strFactory,  // Psr\Http\Message\StreamFactoryInterface
);
```

## How It Works

`Psr18Transport` sends the request and waits for the complete response before returning. This is the standard behavior for non-streaming use cases.

For streaming (where you want tokens as they arrive), use [GuzzleStreamTransport](/http/guzzle-stream).

## Setting as Default

```php
Factory::setDefaultTransport($transport);

// All subsequent useLlm() calls will use this transport
$llm = useLlm('openai.gpt-4o-mini', ['apiKey' => '...']);
```
