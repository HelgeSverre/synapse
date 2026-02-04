<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Hooks\Events;

use HelgeSverre\Synapse\Provider\Request\GenerationRequest;

final readonly class OnError
{
    public function __construct(
        public \Throwable $error,
        public ?GenerationRequest $request = null,
    ) {}
}
