<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Runtime\Memory;

final readonly class MemoryEntry
{
    /**
     * @param  array<string>  $tags
     */
    public function __construct(
        public string $namespace,
        public string $key,
        public mixed $value,
        public array $tags = [],
        public \DateTimeImmutable $updatedAt = new \DateTimeImmutable,
    ) {}
}
