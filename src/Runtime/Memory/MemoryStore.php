<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Runtime\Memory;

interface MemoryStore
{
    /**
     * @param  array<string>  $tags
     */
    public function put(string $namespace, string $key, mixed $value, array $tags = []): MemoryEntry;

    public function get(string $namespace, string $key): ?MemoryEntry;

    public function forget(string $namespace, string $key): void;

    /** @return list<MemoryEntry> */
    public function list(string $namespace): array;

    /** @return list<MemoryEntry> */
    public function searchByTag(string $namespace, string $tag): array;
}
