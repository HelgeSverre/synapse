<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\Profilinator2000;

use HelgeSverre\Synapse\Executor\CallableExecutor;
use HelgeSverre\Synapse\Executor\ToolRegistry;

final class ToolCatalog
{
    /** @var array<string, bool> */
    private array $called = [];

    public function __construct(
        private readonly CdpAdapterInterface $cdp,
        private readonly ReportWriter $reportWriter,
        private readonly string $outputDir = '.',
    ) {}

    public function createToolRegistry(): ToolRegistry
    {
        return new ToolRegistry($this->all());
    }

    /** @return list<CallableExecutor> */
    public function all(): array
    {
        return [
            $this->navigateToUrl(),
            $this->getPerformanceMetrics(),
            $this->startCpuProfile(),
            $this->stopCpuProfile(),
            $this->startTracing(),
            $this->stopTracing(),
            $this->takeHeapSnapshot(),
            $this->evaluateJavascript(),
            $this->getDomTree(),
            $this->getNetworkLog(),
            $this->clickElement(),
            $this->scrollPage(),
            $this->setCpuThrottle(),
            $this->saveReport(),
        ];
    }

    private function navigateToUrl(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'navigate_to_url',
            description: 'Navigate to URL and wait for page lifecycle milestone',
            handler: function (array $input): array {
                $this->called['navigate_to_url'] = true;

                return $this->cdp->navigate(
                    url: (string) $input['url'],
                    waitUntil: (string) ($input['wait_until'] ?? 'networkidle'),
                );
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'url' => ['type' => 'string'],
                    'wait_until' => [
                        'type' => 'string',
                        'enum' => ['load', 'domcontentloaded', 'networkidle'],
                    ],
                ],
                'required' => ['url'],
            ],
            validateInputHandler: fn (array $input): array => isset($input['url']) && is_string($input['url']) && $input['url'] !== ''
                ? ['valid' => true, 'errors' => []]
                : ['valid' => false, 'errors' => ['url is required and must be a non-empty string']],
        );
    }

    private function getPerformanceMetrics(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'get_performance_metrics',
            description: 'Collect runtime metrics across multiple runs',
            handler: function (array $input): array {
                $this->called['get_performance_metrics'] = true;

                return $this->cdp->getPerformanceMetrics(
                    runs: (int) ($input['runs'] ?? 5),
                    warmupRuns: (int) ($input['warmup_runs'] ?? 2),
                    reloadBetweenRuns: (bool) ($input['reload_between_runs'] ?? true),
                );
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'runs' => ['type' => 'integer'],
                    'warmup_runs' => ['type' => 'integer'],
                    'reload_between_runs' => ['type' => 'boolean'],
                ],
            ],
        );
    }

    private function startCpuProfile(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'start_cpu_profile',
            description: 'Start CPU profiling',
            handler: function (array $input): array {
                $this->called['start_cpu_profile'] = true;

                return $this->cdp->startCpuProfile((int) ($input['sampling_interval_us'] ?? 100));
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'sampling_interval_us' => ['type' => 'integer'],
                ],
            ],
        );
    }

    private function stopCpuProfile(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'stop_cpu_profile',
            description: 'Stop CPU profiling and return hotspots',
            handler: function (): array {
                $this->called['stop_cpu_profile'] = true;

                return $this->cdp->stopCpuProfile();
            },
            parameters: ['type' => 'object', 'properties' => []],
        );
    }

    private function startTracing(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'start_tracing',
            description: 'Start tracing timeline events',
            handler: function (array $input): array {
                $this->called['start_tracing'] = true;

                return $this->cdp->startTracing((string) ($input['categories'] ?? 'standard'));
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'categories' => ['type' => 'string', 'enum' => ['standard', 'deep']],
                ],
            ],
        );
    }

    private function stopTracing(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'stop_tracing',
            description: 'Stop tracing and summarize events',
            handler: function (): array {
                $this->called['stop_tracing'] = true;

                return $this->cdp->stopTracing();
            },
            parameters: ['type' => 'object', 'properties' => []],
        );
    }

    private function takeHeapSnapshot(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'take_heap_snapshot',
            description: 'Take and summarize heap snapshot',
            handler: function (): array {
                $this->called['take_heap_snapshot'] = true;

                return $this->cdp->takeHeapSnapshot();
            },
            parameters: ['type' => 'object', 'properties' => []],
        );
    }

    private function evaluateJavascript(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'evaluate_javascript',
            description: 'Evaluate JavaScript in page context',
            handler: function (array $input): array {
                $this->called['evaluate_javascript'] = true;

                return $this->cdp->evaluateJavascript(
                    expression: (string) $input['expression'],
                    returnByValue: (bool) ($input['return_by_value'] ?? true),
                );
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'expression' => ['type' => 'string'],
                    'return_by_value' => ['type' => 'boolean'],
                ],
                'required' => ['expression'],
            ],
            validateInputHandler: fn (array $input): array => isset($input['expression']) && is_string($input['expression']) && $input['expression'] !== ''
                ? ['valid' => true, 'errors' => []]
                : ['valid' => false, 'errors' => ['expression is required and must be a non-empty string']],
        );
    }

    private function getDomTree(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'get_dom_tree',
            description: 'Inspect DOM complexity summary',
            handler: function (array $input): array {
                $this->called['get_dom_tree'] = true;

                return $this->cdp->getDomTree(
                    depth: (int) ($input['depth'] ?? 4),
                    selector: isset($input['selector']) ? (string) $input['selector'] : null,
                );
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'depth' => ['type' => 'integer'],
                    'selector' => ['type' => 'string'],
                ],
            ],
        );
    }

    private function getNetworkLog(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'get_network_log',
            description: 'Get captured network request summary',
            handler: function (): array {
                $this->called['get_network_log'] = true;

                return $this->cdp->getNetworkLog();
            },
            parameters: ['type' => 'object', 'properties' => []],
        );
    }

    private function clickElement(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'click_element',
            description: 'Click element by CSS selector',
            handler: function (array $input): array {
                $this->called['click_element'] = true;

                return $this->cdp->clickElement((string) $input['selector']);
            },
            parameters: [
                'type' => 'object',
                'properties' => ['selector' => ['type' => 'string']],
                'required' => ['selector'],
            ],
            validateInputHandler: fn (array $input): array => isset($input['selector']) && is_string($input['selector']) && $input['selector'] !== ''
                ? ['valid' => true, 'errors' => []]
                : ['valid' => false, 'errors' => ['selector is required and must be a non-empty string']],
        );
    }

    private function scrollPage(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'scroll_page',
            description: 'Scroll page or container',
            handler: function (array $input): array {
                $this->called['scroll_page'] = true;

                return $this->cdp->scrollPage(
                    direction: (string) $input['direction'],
                    pixels: (int) ($input['pixels'] ?? 500),
                    selector: isset($input['selector']) ? (string) $input['selector'] : null,
                );
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'direction' => ['type' => 'string', 'enum' => ['up', 'down']],
                    'pixels' => ['type' => 'integer'],
                    'selector' => ['type' => 'string'],
                ],
                'required' => ['direction'],
            ],
            validateInputHandler: function (array $input): array {
                if (! isset($input['direction']) || ! is_string($input['direction'])) {
                    return ['valid' => false, 'errors' => ['direction is required and must be a string']];
                }
                if (! in_array($input['direction'], ['up', 'down'], true)) {
                    return ['valid' => false, 'errors' => ['direction must be "up" or "down"']];
                }

                return ['valid' => true, 'errors' => []];
            },
        );
    }

    private function setCpuThrottle(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'set_cpu_throttle',
            description: 'Set CPU throttle rate (1=normal, 4=slow)',
            handler: function (array $input): array {
                $this->called['set_cpu_throttle'] = true;

                return $this->cdp->setCpuThrottle((float) $input['rate']);
            },
            parameters: [
                'type' => 'object',
                'properties' => ['rate' => ['type' => 'number']],
                'required' => ['rate'],
            ],
            validateInputHandler: function (array $input): array {
                if (! isset($input['rate']) || ! is_numeric($input['rate'])) {
                    return ['valid' => false, 'errors' => ['rate is required and must be numeric']];
                }
                if ((float) $input['rate'] <= 0) {
                    return ['valid' => false, 'errors' => ['rate must be greater than zero']];
                }

                return ['valid' => true, 'errors' => []];
            },
        );
    }

    private function saveReport(): CallableExecutor
    {
        return new CallableExecutor(
            name: 'save_report',
            description: 'Persist PERFORMANCE-REPORT.md',
            handler: function (array $input): array {
                $this->called['save_report'] = true;

                if (! $this->hasVerificationSignal()) {
                    return [
                        'status' => 'error',
                        'error' => 'verification_required',
                        'hint' => 'Call evaluate_javascript or get_dom_tree before save_report.',
                    ];
                }

                return $this->reportWriter->save(
                    markdownContent: (string) $input['markdown_content'],
                    outputDir: $this->outputDir,
                );
            },
            parameters: [
                'type' => 'object',
                'properties' => [
                    'markdown_content' => ['type' => 'string'],
                ],
                'required' => ['markdown_content'],
            ],
            validateInputHandler: fn (array $input): array => isset($input['markdown_content']) && is_string($input['markdown_content']) && trim($input['markdown_content']) !== ''
                ? ['valid' => true, 'errors' => []]
                : ['valid' => false, 'errors' => ['markdown_content is required and must be non-empty']],
        );
    }

    private function hasVerificationSignal(): bool
    {
        return isset($this->called['evaluate_javascript']) || isset($this->called['get_dom_tree']);
    }
}
