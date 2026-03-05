<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Runtime\Checkpoint;

final readonly class RunCheckpoint
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $runId,
        public string $key,
        public array $payload,
        public array $metadata = [],
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable,
    ) {}
}
