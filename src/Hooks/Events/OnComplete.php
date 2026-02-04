<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Hooks\Events;

final readonly class OnComplete
{
    public function __construct(
        public bool $success,
        public float $durationMs,
        public ?\Throwable $error = null,
    ) {}
}
