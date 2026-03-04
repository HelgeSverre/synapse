<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\Profilinator2000;

/**
 * Scaffold for wiring a real CDP client.
 */
final class RealCdpAdapter implements CdpAdapterInterface
{
    /** @return array<string, mixed> */
    public function navigate(string $url, string $waitUntil = 'networkidle'): array
    {
        throw new \RuntimeException($this->message());
    }

    /** @return array<string, mixed> */
    public function getPerformanceMetrics(int $runs = 5, int $warmupRuns = 2, bool $reloadBetweenRuns = true): array
    {
        throw new \RuntimeException($this->message());
    }

    /** @return array<string, mixed> */
    public function startCpuProfile(int $samplingIntervalUs = 100): array
    {
        throw new \RuntimeException($this->message());
    }

    /** @return array<string, mixed> */
    public function stopCpuProfile(): array
    {
        throw new \RuntimeException($this->message());
    }

    /** @return array<string, mixed> */
    public function startTracing(string $categories = 'standard'): array
    {
        throw new \RuntimeException($this->message());
    }

    /** @return array<string, mixed> */
    public function stopTracing(): array
    {
        throw new \RuntimeException($this->message());
    }

    /** @return array<string, mixed> */
    public function takeHeapSnapshot(): array
    {
        throw new \RuntimeException($this->message());
    }

    /** @return array<string, mixed> */
    public function evaluateJavascript(string $expression, bool $returnByValue = true): array
    {
        throw new \RuntimeException($this->message());
    }

    /** @return array<string, mixed> */
    public function getDomTree(int $depth = 4, ?string $selector = null): array
    {
        throw new \RuntimeException($this->message());
    }

    /** @return array<string, mixed> */
    public function getNetworkLog(): array
    {
        throw new \RuntimeException($this->message());
    }

    /** @return array<string, mixed> */
    public function clickElement(string $selector): array
    {
        throw new \RuntimeException($this->message());
    }

    /** @return array<string, mixed> */
    public function scrollPage(string $direction, int $pixels = 500, ?string $selector = null): array
    {
        throw new \RuntimeException($this->message());
    }

    /** @return array<string, mixed> */
    public function setCpuThrottle(float $rate): array
    {
        throw new \RuntimeException($this->message());
    }

    private function message(): string
    {
        return 'Real CDP adapter is not wired in this example. Use FakeCdpAdapter or implement RealCdpAdapter.';
    }
}
