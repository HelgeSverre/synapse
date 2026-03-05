<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Evaluation;

final class FilesystemSnapshotStore implements SnapshotStoreInterface
{
    public function __construct(
        private readonly string $baseDirectory,
    ) {}

    public function has(string $suite, string $case): bool
    {
        return is_file($this->getPath($suite, $case));
    }

    public function load(string $suite, string $case): mixed
    {
        $path = $this->getPath($suite, $case);
        if (! is_file($path)) {
            throw new \RuntimeException("Snapshot not found: {$suite}/{$case}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Failed to read snapshot file: {$path}");
        }

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    public function save(string $suite, string $case, mixed $value): void
    {
        $path = $this->getPath($suite, $case);
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Failed to create snapshot directory: {$directory}");
        }

        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException("Failed to encode snapshot payload for {$suite}/{$case}");
        }

        if (file_put_contents($path, $json."\n") === false) {
            throw new \RuntimeException("Failed to write snapshot file: {$path}");
        }
    }

    private function getPath(string $suite, string $case): string
    {
        $suiteSafe = $this->encodeSegment($suite);
        $caseSafe = $this->encodeSegment($case);

        return rtrim($this->baseDirectory, '/')."/{$suiteSafe}/{$caseSafe}.json";
    }

    private function encodeSegment(string $value): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $value);
        $prefix = trim($sanitized ?? '', '_') ?: 'snapshot';
        $hash = substr(sha1($value), 0, 12);

        return "{$prefix}--{$hash}";
    }
}
