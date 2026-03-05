<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Examples;

use Generator;
use HelgeSverre\Synapse\Examples\Profilinator2000\FakeCdpAdapter;
use HelgeSverre\Synapse\Examples\Profilinator2000\PerfAgentLoop;
use HelgeSverre\Synapse\Examples\Profilinator2000\ReportWriter;
use HelgeSverre\Synapse\Examples\Profilinator2000\SafeToolExecutor;
use HelgeSverre\Synapse\Examples\Profilinator2000\ToolCatalog;
use HelgeSverre\Synapse\Executor\ToolExecutorInterface;
use HelgeSverre\Synapse\Provider\ProviderCapabilities;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Request\ToolCall;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\State\ConversationState;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\StreamableProviderInterface;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\StreamContext;
use HelgeSverre\Synapse\Streaming\StreamEvent;
use HelgeSverre\Synapse\Streaming\TextDelta;
use HelgeSverre\Synapse\Streaming\ToolCallsReady;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../../examples/profilinator2000/CdpAdapterInterface.php';
require_once __DIR__.'/../../../examples/profilinator2000/FakeCdpAdapter.php';
require_once __DIR__.'/../../../examples/profilinator2000/RealCdpAdapter.php';
require_once __DIR__.'/../../../examples/profilinator2000/ReportWriter.php';
require_once __DIR__.'/../../../examples/profilinator2000/RunResult.php';
require_once __DIR__.'/../../../examples/profilinator2000/TaskPromptBuilder.php';
require_once __DIR__.'/../../../examples/profilinator2000/ToolCatalog.php';
require_once __DIR__.'/../../../examples/profilinator2000/SafeToolExecutor.php';
require_once __DIR__.'/../../../examples/profilinator2000/PerfAgentLoop.php';

final class SequenceStreamProvider implements StreamableProviderInterface
{
    /** @var list<list<StreamEvent>> */
    public array $turns = [];

    private int $index = 0;

    public function generate(GenerationRequest $request): GenerationResponse
    {
        return new GenerationResponse(
            text: '',
            messages: [Message::assistant('')],
            toolCalls: [],
            model: 'test-model',
        );
    }

    public function stream(GenerationRequest $request, ?StreamContext $ctx = null): Generator
    {
        $events = $this->turns[$this->index] ?? [];
        $this->index++;

        foreach ($events as $event) {
            yield $event;
        }
    }

    public function getCapabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(supportsStreaming: true, supportsTools: true);
    }

    public function getName(): string
    {
        return 'sequence-stream-provider';
    }
}

final class Profilinator2000Test extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/synapse-profilinator-'.uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $file = $this->tmpDir.'/PERFORMANCE-REPORT.md';
        if (file_exists($file)) {
            unlink($file);
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function test_tool_validation_rejects_invalid_inputs(): void
    {
        $catalog = new ToolCatalog(new FakeCdpAdapter, new ReportWriter, $this->tmpDir);
        $tools = $catalog->createToolRegistry();

        $missingUrl = $tools->validateFunctionInput('navigate_to_url', []);
        $this->assertFalse($missingUrl['valid']);

        $missingContent = $tools->validateFunctionInput('save_report', []);
        $this->assertFalse($missingContent['valid']);
    }

    public function test_save_report_requires_verification_call_first(): void
    {
        $writer = new ReportWriter;
        $catalog = new ToolCatalog(new FakeCdpAdapter, $writer, $this->tmpDir);
        $tools = $catalog->createToolRegistry();

        $result = $tools->callFunctionResult('save_report', [
            'markdown_content' => '# Performance Report',
        ]);

        $this->assertTrue($result->success);
        $this->assertIsArray($result->result);
        $this->assertSame('error', $result->result['status']);
        $this->assertFalse($writer->hasSavedReport());
    }

    public function test_perf_agent_loop_saves_report_and_stops(): void
    {
        $provider = new SequenceStreamProvider;
        $provider->turns = [
            [
                new ToolCallsReady([new ToolCall('call_1', 'navigate_to_url', ['url' => 'https://example.com'])]),
                new StreamCompleted('tool_calls'),
            ],
            [
                new ToolCallsReady([new ToolCall('call_2', 'evaluate_javascript', ['expression' => 'document.title'])]),
                new StreamCompleted('tool_calls'),
            ],
            [
                new ToolCallsReady([new ToolCall('call_3', 'save_report', [
                    'markdown_content' => "# Performance Report: https://example.com\n\n## Executive Summary\n- OK\n",
                ])]),
                new StreamCompleted('tool_calls'),
            ],
            [
                new TextDelta('Report saved.'),
                new StreamCompleted('stop'),
            ],
        ];

        $writer = new ReportWriter;
        $catalog = new ToolCatalog(new FakeCdpAdapter, $writer, $this->tmpDir);
        $safeTools = new SafeToolExecutor($catalog->createToolRegistry(), 4096);

        $loop = new PerfAgentLoop(
            provider: $provider,
            tools: $safeTools,
            reportWriter: $writer,
            model: 'test-model',
            maxToolIterations: 8,
        );

        $result = $loop->run('https://example.com', 'calendar rendering', 3);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->turns);
        $this->assertNotNull($result->reportPath);
        $this->assertFileExists($result->reportPath);
    }

    public function test_safe_tool_executor_truncates_large_payloads(): void
    {
        $inner = new class implements ToolExecutorInterface
        {
            public function getToolDefinitions(): array
            {
                return [];
            }

            public function callFunctionResult(string $name, array $input, ?ConversationState $state = null): \HelgeSverre\Synapse\Executor\ToolResult
            {
                return \HelgeSverre\Synapse\Executor\ToolResult::success(str_repeat('x', 2000));
            }
        };

        $safe = new SafeToolExecutor($inner, 120);
        $result = $safe->callFunctionResult('huge_tool', []);

        $this->assertTrue($result->success);
        $this->assertIsString($result->result);
        $this->assertStringContainsString('truncated tool output', $result->result);
    }
}
