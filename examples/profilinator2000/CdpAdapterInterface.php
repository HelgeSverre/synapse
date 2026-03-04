<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\Profilinator2000;

/**
 * Abstraction over browser profiling capabilities.
 */
interface CdpAdapterInterface
{
    /** @return array<string, mixed> */
    public function navigate(string $url, string $waitUntil = 'networkidle'): array;

    /** @return array<string, mixed> */
    public function getPerformanceMetrics(int $runs = 5, int $warmupRuns = 2, bool $reloadBetweenRuns = true): array;

    /** @return array<string, mixed> */
    public function startCpuProfile(int $samplingIntervalUs = 100): array;

    /** @return array<string, mixed> */
    public function stopCpuProfile(): array;

    /** @return array<string, mixed> */
    public function startTracing(string $categories = 'standard'): array;

    /** @return array<string, mixed> */
    public function stopTracing(): array;

    /** @return array<string, mixed> */
    public function takeHeapSnapshot(): array;

    /** @return array<string, mixed> */
    public function evaluateJavascript(string $expression, bool $returnByValue = true): array;

    /** @return array<string, mixed> */
    public function getDomTree(int $depth = 4, ?string $selector = null): array;

    /** @return array<string, mixed> */
    public function getNetworkLog(): array;

    /** @return array<string, mixed> */
    public function clickElement(string $selector): array;

    /** @return array<string, mixed> */
    public function scrollPage(string $direction, int $pixels = 500, ?string $selector = null): array;

    /** @return array<string, mixed> */
    public function setCpuThrottle(float $rate): array;
}
