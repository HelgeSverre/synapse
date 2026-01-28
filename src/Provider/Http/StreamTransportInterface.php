<?php

declare(strict_types=1);

namespace LlmExe\Provider\Http;

use LlmExe\Streaming\StreamContext;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface for transports that support streaming responses.
 */
interface StreamTransportInterface extends TransportInterface
{
    /**
     * Make a POST request and return a streaming response.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     * @return ResponseInterface The raw PSR-7 response for streaming
     */
    public function streamPost(string $url, array $headers, array $body, ?StreamContext $ctx = null): ResponseInterface;
}
