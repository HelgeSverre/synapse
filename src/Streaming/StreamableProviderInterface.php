<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Streaming;

use Generator;
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;

/**
 * Interface for providers that support streaming responses.
 *
 * Extends LlmProviderInterface to add streaming capability.
 * Check provider capabilities with getCapabilities()->supportsStreaming
 * before calling stream().
 */
interface StreamableProviderInterface extends LlmProviderInterface
{
    /**
     * Generate a streaming response.
     *
     * @return Generator<StreamEvent> Yields TextDelta, ToolCallDelta, ToolCallsReady, StreamCompleted events
     */
    public function stream(GenerationRequest $request, ?StreamContext $ctx = null): Generator;
}
