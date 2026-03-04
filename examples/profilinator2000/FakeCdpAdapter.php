<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\Profilinator2000;

final class FakeCdpAdapter implements CdpAdapterInterface
{
    private string $currentUrl = 'about:blank';

    private bool $cpuProfileRunning = false;

    private bool $tracingRunning = false;

    private float $cpuThrottleRate = 1.0;

    /** @var list<array<string, mixed>> */
    private array $operationLog = [];

    /** @return list<array<string, mixed>> */
    public function getOperationLog(): array
    {
        return $this->operationLog;
    }

    /** @return array<string, mixed> */
    public function navigate(string $url, string $waitUntil = 'networkidle'): array
    {
        $this->currentUrl = $url;
        $this->record('navigate_to_url', ['url' => $url, 'wait_until' => $waitUntil]);

        return [
            'status' => 'ok',
            'url' => $url,
            'wait_until' => $waitUntil,
            'load_ms' => 820,
            'network_idle_ms' => 1180,
        ];
    }

    /** @return array<string, mixed> */
    public function getPerformanceMetrics(int $runs = 5, int $warmupRuns = 2, bool $reloadBetweenRuns = true): array
    {
        $this->record('get_performance_metrics', [
            'runs' => $runs,
            'warmup_runs' => $warmupRuns,
            'reload_between_runs' => $reloadBetweenRuns,
        ]);

        return [
            'status' => 'ok',
            'url' => $this->currentUrl,
            'configuration' => [
                'runs' => $runs,
                'warmup_runs' => $warmupRuns,
                'reload_between_runs' => $reloadBetweenRuns,
                'cpu_throttle_rate' => $this->cpuThrottleRate,
            ],
            'metrics' => [
                'LCP_ms' => ['median' => 2410, 'mean' => 2463, 'stddev' => 118, 'min' => 2310, 'max' => 2680],
                'INP_ms' => ['median' => 184, 'mean' => 193, 'stddev' => 16, 'min' => 171, 'max' => 224],
                'CLS' => ['median' => 0.06, 'mean' => 0.07, 'stddev' => 0.01, 'min' => 0.05, 'max' => 0.09],
                'long_tasks' => ['count' => 3, 'largest_ms' => 123],
                'script_duration_ms' => ['median' => 492, 'mean' => 507, 'stddev' => 35, 'min' => 460, 'max' => 571],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function startCpuProfile(int $samplingIntervalUs = 100): array
    {
        $this->cpuProfileRunning = true;
        $this->record('start_cpu_profile', ['sampling_interval_us' => $samplingIntervalUs]);

        return ['status' => 'ok', 'sampling_interval_us' => $samplingIntervalUs];
    }

    /** @return array<string, mixed> */
    public function stopCpuProfile(): array
    {
        $this->record('stop_cpu_profile', []);

        if (! $this->cpuProfileRunning) {
            return ['status' => 'error', 'error' => 'CPU profiler is not running'];
        }

        $this->cpuProfileRunning = false;

        return [
            'status' => 'ok',
            'top_functions' => [
                ['name' => 'renderCalendarGrid', 'total_ms' => 212.4, 'self_ms' => 143.2],
                ['name' => 'computeVisibleEvents', 'total_ms' => 98.1, 'self_ms' => 72.0],
                ['name' => 'formatDateLabel', 'total_ms' => 41.7, 'self_ms' => 38.6],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function startTracing(string $categories = 'standard'): array
    {
        $this->tracingRunning = true;
        $this->record('start_tracing', ['categories' => $categories]);

        return ['status' => 'ok', 'categories' => $categories];
    }

    /** @return array<string, mixed> */
    public function stopTracing(): array
    {
        $this->record('stop_tracing', []);

        if (! $this->tracingRunning) {
            return ['status' => 'error', 'error' => 'Tracing is not running'];
        }

        $this->tracingRunning = false;

        return [
            'status' => 'ok',
            'summary' => [
                'long_tasks_over_50ms' => 5,
                'forced_layout_events' => 11,
                'paint_events' => 36,
                'frame_drops' => 8,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function takeHeapSnapshot(): array
    {
        $this->record('take_heap_snapshot', []);

        return [
            'status' => 'ok',
            'heap_mb' => 24.6,
            'dom_node_count' => 2214,
            'top_allocators' => [
                ['name' => 'CalendarEventCard', 'retained_mb' => 5.2],
                ['name' => 'DateCellComponent', 'retained_mb' => 3.7],
                ['name' => 'VirtualListBuffer', 'retained_mb' => 2.9],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function evaluateJavascript(string $expression, bool $returnByValue = true): array
    {
        $this->record('evaluate_javascript', ['expression' => $expression, 'return_by_value' => $returnByValue]);

        return [
            'status' => 'ok',
            'result' => [
                'title' => 'Profilinator Demo App',
                'framework' => 'react',
                'interactive_elements' => 37,
                'expression_echo' => $expression,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function getDomTree(int $depth = 4, ?string $selector = null): array
    {
        $this->record('get_dom_tree', ['depth' => $depth, 'selector' => $selector]);

        return [
            'status' => 'ok',
            'summary' => [
                'total_nodes' => 2214,
                'max_depth' => 26,
                'max_children' => 48,
                'selector' => $selector,
                'sample_element_counts' => [
                    'div' => 1062,
                    'button' => 74,
                    'span' => 310,
                    'svg' => 48,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function getNetworkLog(): array
    {
        $this->record('get_network_log', []);

        return [
            'status' => 'ok',
            'summary' => [
                'request_count' => 63,
                'total_kb' => 1582,
                'largest_resources' => [
                    ['url' => '/assets/main.js', 'size_kb' => 412],
                    ['url' => '/assets/vendor.js', 'size_kb' => 381],
                    ['url' => '/assets/calendar.css', 'size_kb' => 114],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function clickElement(string $selector): array
    {
        $this->record('click_element', ['selector' => $selector]);

        return ['status' => 'ok', 'selector' => $selector];
    }

    /** @return array<string, mixed> */
    public function scrollPage(string $direction, int $pixels = 500, ?string $selector = null): array
    {
        $this->record('scroll_page', ['direction' => $direction, 'pixels' => $pixels, 'selector' => $selector]);

        return [
            'status' => 'ok',
            'direction' => $direction,
            'pixels' => $pixels,
            'selector' => $selector,
        ];
    }

    /** @return array<string, mixed> */
    public function setCpuThrottle(float $rate): array
    {
        $this->cpuThrottleRate = max(0.1, $rate);
        $this->record('set_cpu_throttle', ['rate' => $this->cpuThrottleRate]);

        return ['status' => 'ok', 'rate' => $this->cpuThrottleRate];
    }

    /** @param array<string, mixed> $arguments */
    private function record(string $tool, array $arguments): void
    {
        $this->operationLog[] = [
            'tool' => $tool,
            'arguments' => $arguments,
            'time' => date(DATE_ATOM),
        ];
    }
}
