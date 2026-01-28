<?php

declare(strict_types=1);

namespace LlmExe\Hooks\Events;

final readonly class OnComplete
{
    public function __construct(
        public bool $success,
        public float $durationMs,
        public ?\Throwable $error = null,
    ) {}
}
