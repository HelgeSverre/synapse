<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Trace;

final readonly class TraceContext
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $traceId,
        public ?string $runId = null,
        public ?string $parentSpanId = null,
        public array $attributes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function root(array $attributes = [], ?string $traceId = null, ?string $runId = null): self
    {
        return new self(
            traceId: $traceId ?? self::generateId(16),
            runId: $runId ?? self::generateId(8),
            parentSpanId: null,
            attributes: $attributes,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function child(?string $parentSpanId, array $attributes = []): self
    {
        return new self(
            traceId: $this->traceId,
            runId: $this->runId,
            parentSpanId: $parentSpanId,
            attributes: [...$this->attributes, ...$attributes],
        );
    }

    public function withAttribute(string $key, mixed $value): self
    {
        return new self(
            traceId: $this->traceId,
            runId: $this->runId,
            parentSpanId: $this->parentSpanId,
            attributes: [...$this->attributes, $key => $value],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withAttributes(array $attributes): self
    {
        return new self(
            traceId: $this->traceId,
            runId: $this->runId,
            parentSpanId: $this->parentSpanId,
            attributes: [...$this->attributes, ...$attributes],
        );
    }

    /**
     * @param  positive-int  $bytes
     */
    private static function generateId(int $bytes): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
