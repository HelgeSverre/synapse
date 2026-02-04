<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Provider;

final readonly class ProviderCapabilities
{
    public function __construct(
        public bool $supportsTools = false,
        public bool $supportsJsonMode = false,
        public bool $supportsStreaming = false,
        public bool $supportsVision = false,
        public bool $supportsSystemPrompt = true,
        public ?int $maxContextLength = null,
    ) {}
}
