<?php

declare(strict_types=1);

namespace LlmExe\Hooks\Events;

use LlmExe\Provider\Request\GenerationRequest;

/**
 * Dispatched when a streaming request begins.
 */
final readonly class OnStreamStart
{
    public function __construct(
        public GenerationRequest $request,
    ) {}
}
