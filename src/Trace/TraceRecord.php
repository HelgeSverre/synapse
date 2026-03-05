<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Trace;

final readonly class TraceRecord
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $traceId,
        public ?string $runId,
        public string $spanId,
        public ?string $parentSpanId,
        public string $name,
        public float $startedAtMs,
        public float $endedAtMs,
        public bool $success,
        public array $attributes = [],
        public ?string $error = null,
    ) {}

    public function durationMs(): float
    {
        return max(0.0, $this->endedAtMs - $this->startedAtMs);
    }
}
