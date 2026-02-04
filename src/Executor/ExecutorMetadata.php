<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Executor;

final readonly class ExecutorMetadata
{
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public \DateTimeImmutable $created,
        public int $executions = 0,
        public ?string $traceId = null,
    ) {}

    public static function create(string $type, ?string $name = null): self
    {
        return new self(
            id: bin2hex(random_bytes(16)),
            type: $type,
            name: $name ?? $type,
            created: new \DateTimeImmutable,
        );
    }

    public function withExecution(): self
    {
        return new self(
            $this->id,
            $this->type,
            $this->name,
            $this->created,
            $this->executions + 1,
            $this->traceId,
        );
    }

    public function withTraceId(string $traceId): self
    {
        return new self(
            $this->id,
            $this->type,
            $this->name,
            $this->created,
            $this->executions,
            $traceId,
        );
    }
}
