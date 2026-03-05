<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Evaluation;

final class InMemorySnapshotStore implements SnapshotStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $snapshots = [];

    public function has(string $suite, string $case): bool
    {
        return array_key_exists($case, $this->snapshots[$suite] ?? []);
    }

    public function load(string $suite, string $case): mixed
    {
        if (! $this->has($suite, $case)) {
            throw new \RuntimeException("Snapshot not found: {$suite}/{$case}");
        }

        return $this->snapshots[$suite][$case];
    }

    public function save(string $suite, string $case, mixed $value): void
    {
        $this->snapshots[$suite][$case] = $value;
    }
}
