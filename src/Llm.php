<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse;

use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\Streaming\StreamableProviderInterface;
use HelgeSverre\Synapse\Streaming\StreamContext;
use HelgeSverre\Synapse\Streaming\StreamEvent;

/**
 * Wraps an LLM provider with a default model.
 *
 * Returned by useLlm('provider.model') — carries both the provider instance
 * and the model name so executors can use them without redundant configuration.
 */
final readonly class Llm implements LlmProviderInterface, StreamableProviderInterface
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

    public function stream(GenerationRequest $request, ?StreamContext $ctx = null): \Generator
    {
        if (! $this->provider instanceof StreamableProviderInterface) {
            throw new \RuntimeException("Provider '{$this->provider->getName()}' does not support streaming.");
        }

        /** @var \Generator<StreamEvent> $stream */
        $stream = $this->provider->stream($request, $ctx);

        return $stream;
    }
}
