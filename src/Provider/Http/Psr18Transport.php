<?php

declare(strict_types=1);

namespace LlmExe\Provider\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class Psr18Transport implements TransportInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    /** @return array<string, mixed> */
    public function post(string $url, array $headers, array $body): array
    {
        $request = $this->requestFactory->createRequest('POST', $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $stream = $this->streamFactory->createStream($jsonBody);
        $request = $request->withBody($stream);

        $response = $this->client->sendRequest($request);
        $responseBody = (string) $response->getBody();

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException(
                "HTTP {$response->getStatusCode()}: {$responseBody}",
                $response->getStatusCode(),
            );
        }

        return json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    }
}
