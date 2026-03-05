<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Evaluation;

interface SnapshotStoreInterface
{
    public function has(string $suite, string $case): bool;

    public function load(string $suite, string $case): mixed;

    public function save(string $suite, string $case, mixed $value): void;
}
