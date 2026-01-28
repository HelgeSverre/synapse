<?php

declare(strict_types=1);

namespace LlmExe\Embeddings\Cohere;

use LlmExe\Embeddings\EmbeddingProviderInterface;
use LlmExe\Embeddings\EmbeddingResponse;
use LlmExe\Provider\Http\TransportInterface;

final readonly class CohereEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        private TransportInterface $transport,
        private string $apiKey,
        private string $baseUrl = 'https://api.cohere.com/v2',
    ) {}

    public function embed(string|array $input, string $model, array $options = []): EmbeddingResponse
    {
        $texts = is_string($input) ? [$input] : $input;

        $body = [
            'model' => $model,
            'texts' => $texts,
            'input_type' => $options['input_type'] ?? 'search_document',
        ];

        if (isset($options['truncate'])) {
            $body['truncate'] = $options['truncate'];
        }

        $response = $this->transport->post(
            $this->baseUrl.'/embed',
            [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ],
            $body,
        );

        $embeddings = $response['embeddings']['float'] ?? [];

        return new EmbeddingResponse(
            embeddings: $embeddings,
            model: $response['model'] ?? $model,
            totalTokens: null,
            raw: $response,
        );
    }

    public function getName(): string
    {
        return 'cohere';
    }
}
