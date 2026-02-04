<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Provider;

use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;

interface LlmProviderInterface
{
    public function generate(GenerationRequest $request): GenerationResponse;

    public function getCapabilities(): ProviderCapabilities;

    public function getName(): string;
}
