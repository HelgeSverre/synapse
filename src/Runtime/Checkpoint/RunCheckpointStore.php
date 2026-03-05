<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Runtime\Checkpoint;

interface RunCheckpointStore
{
    public function save(RunCheckpoint $checkpoint): void;

    public function get(string $runId, string $key): ?RunCheckpoint;

    /** @return list<RunCheckpoint> */
    public function list(string $runId): array;

    public function delete(string $runId, string $key): void;

    public function clearRun(string $runId): void;
}
