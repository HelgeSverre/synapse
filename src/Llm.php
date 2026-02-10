<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse;

use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;

/**
 * Wraps an LLM provider with a default model.
 *
 * Returned by useLlm('provider.model') — carries both the provider instance
 * and the model name so executors can use them without redundant configuration.
 */
final readonly class Llm implements LlmProviderInterface
{
    public function __construct(
        public LlmProviderInterface $provider,
        public ?string $model = null,
    ) {}

    public function generate(GenerationRequest $request): GenerationResponse
    {
        return $this->provider->generate($request);
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return $this->provider->getCapabilities();
    }

    public function getName(): string
    {
        return $this->provider->getName();
    }
}
