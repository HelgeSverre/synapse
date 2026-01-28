<?php

declare(strict_types=1);

namespace LlmExe\Hooks\Events;

use LlmExe\Provider\Request\GenerationRequest;

final readonly class BeforeProviderCall
{
    public function __construct(
        public GenerationRequest $request,
    ) {}
}
