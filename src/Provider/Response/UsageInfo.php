<?php

declare(strict_types=1);

namespace LlmExe\Provider\Response;

final readonly class UsageInfo
{
    public function __construct(
        public int $inputTokens,
        public int $outputTokens,
        public ?int $totalTokens = null,
    ) {}

    public function getTotal(): int
    {
        return $this->totalTokens ?? ($this->inputTokens + $this->outputTokens);
    }
}
