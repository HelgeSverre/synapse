<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Hooks\Events;

use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;

final readonly class AfterProviderCall
{
    public function __construct(
        public GenerationRequest $request,
        public GenerationResponse $response,
    ) {}
}
