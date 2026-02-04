<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Embeddings;

interface EmbeddingProviderInterface
{
    /**
     * @param  string|array<string>  $input
     */
    public function embed(string|array $input, string $model, array $options = []): EmbeddingResponse;

    public function getName(): string;
}
