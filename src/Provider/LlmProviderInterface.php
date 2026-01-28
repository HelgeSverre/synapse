<?php

declare(strict_types=1);

namespace LlmExe\Provider;

use LlmExe\Provider\Request\GenerationRequest;
use LlmExe\Provider\Response\GenerationResponse;

interface LlmProviderInterface
{
    public function generate(GenerationRequest $request): GenerationResponse;

    public function getCapabilities(): ProviderCapabilities;

    public function getName(): string;
}
