<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Runtime\Memory;

final class InMemoryMemoryStore implements MemoryStore
{
    /** @var array<string, array<string, MemoryEntry>> */
    private array $entries = [];

    /**
     * @param  array<string>  $tags
     */
    public function put(string $namespace, string $key, mixed $value, array $tags = []): MemoryEntry
    {
        $entry = new MemoryEntry(
            namespace: $namespace,
            key: $key,
            value: $value,
            tags: array_values(array_unique($tags)),
        );

        $this->entries[$namespace][$key] = $entry;

        return $entry;
    }

    public function get(string $namespace, string $key): ?MemoryEntry
    {
        return $this->entries[$namespace][$key] ?? null;
    }

    public function forget(string $namespace, string $key): void
    {
        unset($this->entries[$namespace][$key]);

        if (($this->entries[$namespace] ?? []) === []) {
            unset($this->entries[$namespace]);
        }
    }

    /** @return list<MemoryEntry> */
    public function list(string $namespace): array
    {
        if (! isset($this->entries[$namespace])) {
            return [];
        }

        return array_values($this->entries[$namespace]);
    }

    /** @return list<MemoryEntry> */
    public function searchByTag(string $namespace, string $tag): array
    {
        return array_values(array_filter(
            $this->list($namespace),
            static fn (MemoryEntry $entry): bool => in_array($tag, $entry->tags, true),
        ));
    }
}
