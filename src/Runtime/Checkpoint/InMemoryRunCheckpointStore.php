<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Runtime\Checkpoint;

final class InMemoryRunCheckpointStore implements RunCheckpointStore
{
    /** @var array<string, array<string, RunCheckpoint>> */
    private array $runs = [];

    public function save(RunCheckpoint $checkpoint): void
    {
        $this->runs[$checkpoint->runId][$checkpoint->key] = $checkpoint;
    }

    public function get(string $runId, string $key): ?RunCheckpoint
    {
        return $this->runs[$runId][$key] ?? null;
    }

    /** @return list<RunCheckpoint> */
    public function list(string $runId): array
    {
        if (! isset($this->runs[$runId])) {
            return [];
        }

        return array_values($this->runs[$runId]);
    }

    public function delete(string $runId, string $key): void
    {
        unset($this->runs[$runId][$key]);

        if (($this->runs[$runId] ?? []) === []) {
            unset($this->runs[$runId]);
        }
    }

    public function clearRun(string $runId): void
    {
        unset($this->runs[$runId]);
    }
}
