<?php

declare(strict_types=1);

namespace LlmExe\Hooks\Events;

use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\Provider\Response\GenerationResponse;

final readonly class AfterProviderCall
{
    public function __construct(
        public GenerationRequest $request,
        public GenerationResponse $response,
    ) {}
}
