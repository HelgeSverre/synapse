<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Embeddings\Ollama;

use HelgeSverre\Synapse\Embeddings\EmbeddingProviderInterface;
use HelgeSverre\Synapse\Embeddings\EmbeddingResponse;
use HelgeSverre\Synapse\Provider\Http\TransportInterface;

final readonly class OllamaEmbeddingProvider implements EmbeddingProviderInterface
{
    private const BASE_URL = 'http://localhost:11434/v1';

    public function __construct(
        private TransportInterface $transport,
        private string $baseUrl = self::BASE_URL,
        private ?string $apiKey = null,
    ) {}

    public function embed(string|array $input, string $model, array $options = []): EmbeddingResponse
    {
        $body = [
            'input' => $input,
            'model' => $model,
        ];

        if (isset($options['dimensions'])) {
            $body['dimensions'] = $options['dimensions'];
        }

        if (isset($options['encoding_format'])) {
            $body['encoding_format'] = $options['encoding_format'];
        }

        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey !== null && $this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer '.$this->apiKey;
        }

        $response = $this->transport->post(
            $this->baseUrl.'/embeddings',
            $headers,
            $body,
        );

        $embeddings = array_map(
            fn (array $item): array => $item['embedding'],
            $response['data'] ?? [],
        );

        return new EmbeddingResponse(
            embeddings: $embeddings,
            model: $response['model'] ?? $model,
            totalTokens: $response['usage']['total_tokens'] ?? null,
            raw: $response,
        );
    }

    public function getName(): string
    {
        return 'ollama';
    }
}
