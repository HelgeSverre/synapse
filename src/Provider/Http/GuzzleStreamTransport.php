<?php

declare(strict_types=1);

namespace LlmExe\Provider\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use LlmExe\Streaming\StreamContext;
use Psr\Http\Message\ResponseInterface;

/**
 * Guzzle-based transport with streaming support.
 *
 * Uses Guzzle's native streaming capability via ['stream' => true].
 */
final readonly class GuzzleStreamTransport implements StreamTransportInterface
{
    public function __construct(
        private ClientInterface $client,
    ) {}

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(string $url, array $headers, array $body): array
    {
        try {
            $response = $this->client->request('POST', $url, [
                RequestOptions::HEADERS => $headers,
                RequestOptions::JSON => $body,
                RequestOptions::HTTP_ERRORS => false,
            ]);
        } catch (RequestException $e) {
            throw new \RuntimeException(
                "HTTP request failed: {$e->getMessage()}",
                $e->getCode(),
                $e,
            );
        }

        $responseBody = (string) $response->getBody();

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException(
                "HTTP {$response->getStatusCode()}: {$responseBody}",
                $response->getStatusCode(),
            );
        }

        return json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     */
    public function streamPost(string $url, array $headers, array $body, ?StreamContext $ctx = null): ResponseInterface
    {
        $options = [
            RequestOptions::HEADERS => $headers,
            RequestOptions::JSON => $body,
            RequestOptions::STREAM => true,
            RequestOptions::HTTP_ERRORS => false,
        ];

        if ($ctx?->timeout !== null) {
            $options[RequestOptions::TIMEOUT] = $ctx->timeout;
        }

        try {
            $response = $this->client->request('POST', $url, $options);
        } catch (RequestException $e) {
            throw new \RuntimeException(
                "HTTP request failed: {$e->getMessage()}",
                $e->getCode(),
                $e,
            );
        }

        if ($response->getStatusCode() >= 400) {
            $responseBody = (string) $response->getBody();

            throw new \RuntimeException(
                "HTTP {$response->getStatusCode()}: {$responseBody}",
                $response->getStatusCode(),
            );
        }

        return $response;
    }
}
