<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Provider\Http;

use HelgeSverre\Synapse\Streaming\StreamContext;
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
