<?php

declare(strict_types=1);

namespace LlmExe\Hooks\Events;

use LlmExe\Provider\Request\GenerationRequest;

final readonly class OnError
{
    public function __construct(
        public \Throwable $error,
        public ?GenerationRequest $request = null,
    ) {}
}
