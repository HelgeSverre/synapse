<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Provider\Http;

interface TransportInterface
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(string $url, array $headers, array $body): array;
}
