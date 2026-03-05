<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Trace;

use HelgeSverre\Synapse\Hooks\Events\AfterProviderCall;
use HelgeSverre\Synapse\Hooks\Events\BeforeProviderCall;
use HelgeSverre\Synapse\Hooks\Events\OnComplete;
use HelgeSverre\Synapse\Hooks\Events\OnStreamEnd;
use HelgeSverre\Synapse\Hooks\Events\OnStreamStart;
use HelgeSverre\Synapse\Hooks\Events\OnToolCall;
use HelgeSverre\Synapse\Hooks\HookDispatcher;
use HelgeSverre\Synapse\Provider\Request\GenerationRequest;
use HelgeSverre\Synapse\Provider\Request\ToolCall;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\Provider\Response\UsageInfo;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Trace\HookTraceBridge;
use HelgeSverre\Synapse\Trace\InMemoryTraceExporter;
use HelgeSverre\Synapse\Trace\TraceContext;
use PHPUnit\Framework\TestCase;

final class HookTraceBridgeTest extends TestCase
{
    public function test_bridge_exports_provider_and_run_spans(): void
    {
        $hooks = new HookDispatcher;
        $exporter = new InMemoryTraceExporter;
        $context = TraceContext::root(['service' => 'unit-test']);

        (new HookTraceBridge($exporter, $context))->register($hooks);

        $request = new GenerationRequest(
            model: 'test-model',
            messages: [Message::user('hello')],
        );
        $response = new GenerationResponse(
            text: 'ok',
            messages: [Message::assistant('ok')],
            toolCalls: [],
            model: 'test-model',
            usage: new UsageInfo(10, 5),
        );

        $hooks->dispatch(new BeforeProviderCall($request));
        $hooks->dispatch(new AfterProviderCall($request, $response));
        $hooks->dispatch(new OnComplete(success: true, durationMs: 12.5));

        $records = $exporter->getRecords();
        $this->assertCount(2, $records);

        $this->assertSame('provider.call', $records[0]->name);
        $this->assertTrue($records[0]->success);
        $this->assertSame('test-model', $records[0]->attributes['model']);
        $this->assertSame(15, $records[0]->attributes['total_tokens']);
        $this->assertSame('unit-test', $records[0]->attributes['service']);

        $this->assertSame('executor.run', $records[1]->name);
        $this->assertTrue($records[1]->success);
        $this->assertSame($context->traceId, $records[1]->traceId);
    }

    public function test_bridge_exports_stream_and_tool_events(): void
    {
        $hooks = new HookDispatcher;
        $exporter = new InMemoryTraceExporter;

        (new HookTraceBridge($exporter))->register($hooks);

        $request = new GenerationRequest(
            model: 'stream-model',
            messages: [Message::user('stream')],
        );

        $hooks->dispatch(new OnStreamStart($request));
        $hooks->dispatch(new OnToolCall(new ToolCall('tc_1', 'search', ['q' => 'php'])));
        $hooks->dispatch(new OnStreamEnd(new StreamCompleted('stop', new UsageInfo(3, 2)), 'done'));
        $hooks->dispatch(new OnComplete(success: true, durationMs: 20.0));

        $records = $exporter->getRecords();
        $names = array_map(static fn ($record): string => $record->name, $records);

        $this->assertContains('stream.call', $names);
        $this->assertContains('tool.call', $names);
        $this->assertContains('executor.run', $names);
    }
}
