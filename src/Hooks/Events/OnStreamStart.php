<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Hooks\Events;

use HelgeSverre\Synapse\Provider\Request\GenerationRequest;

/**
 * Dispatched when a streaming request begins.
 */
final readonly class OnStreamStart
{
    public function __construct(
        public GenerationRequest $request,
    ) {}
}
