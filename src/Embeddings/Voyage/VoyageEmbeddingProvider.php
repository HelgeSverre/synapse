<?php

declare(strict_types=1);

namespace LlmExe\Embeddings\Voyage;

use LlmExe\Embeddings\EmbeddingProviderInterface;
use LlmExe\Embeddings\EmbeddingResponse;
use LlmExe\Provider\Http\TransportInterface;

final readonly class VoyageEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        private TransportInterface $transport,
        private string $apiKey,
        private string $baseUrl = 'https://api.voyageai.com/v1',
    ) {}

    public function embed(string|array $input, string $model, array $options = []): EmbeddingResponse
    {
        $body = [
            'input' => $input,
            'model' => $model,
        ];

        if (isset($options['input_type'])) {
            $body['input_type'] = $options['input_type'];
        }

        if (isset($options['truncation'])) {
            $body['truncation'] = $options['truncation'];
        }

        $response = $this->transport->post(
            $this->baseUrl.'/embeddings',
            [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ],
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
        return 'voyage';
    }
}
