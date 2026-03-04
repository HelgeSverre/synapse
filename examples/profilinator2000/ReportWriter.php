<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\Profilinator2000;

final class ReportWriter
{
    private ?string $savedPath = null;

    private ?string $lastContent = null;

    /** @return array<string, mixed> */
    public function save(string $markdownContent, string $outputDir = '.', string $fileName = 'PERFORMANCE-REPORT.md'): array
    {
        if ($markdownContent === '') {
            return ['status' => 'error', 'error' => 'markdown_content cannot be empty'];
        }

        if (! is_dir($outputDir) && ! @mkdir($outputDir, 0755, true) && ! is_dir($outputDir)) {
            return ['status' => 'error', 'error' => "Unable to create directory: {$outputDir}"];
        }

        $path = rtrim($outputDir, '/').'/'.$fileName;
        $bytes = @file_put_contents($path, $markdownContent);

        if ($bytes === false) {
            return ['status' => 'error', 'error' => "Failed to write report: {$path}"];
        }

        $this->savedPath = $path;
        $this->lastContent = $markdownContent;

        return [
            'status' => 'saved',
            'path' => $path,
            'bytes' => $bytes,
        ];
    }

    public function hasSavedReport(): bool
    {
        return $this->savedPath !== null;
    }

    public function getSavedPath(): ?string
    {
        return $this->savedPath;
    }

    public function getLastContent(): ?string
    {
        return $this->lastContent;
    }
}
