<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Streaming;

use HelgeSverre\Synapse\Provider\Request\ToolCall;
use HelgeSverre\Synapse\Provider\Response\UsageInfo;
use HelgeSverre\Synapse\Streaming\StreamCompleted;
use HelgeSverre\Synapse\Streaming\StreamEvent;
use HelgeSverre\Synapse\Streaming\TextDelta;
use HelgeSverre\Synapse\Streaming\ToolCallDelta;
use HelgeSverre\Synapse\Streaming\ToolCallsReady;
use PHPUnit\Framework\TestCase;

final class StreamEventTest extends TestCase
{
    public function test_text_delta(): void
    {
        $event = new TextDelta('Hello');

        $this->assertInstanceOf(StreamEvent::class, $event);
        $this->assertSame('Hello', $event->text);
    }

    public function test_tool_call_delta(): void
    {
        $event = new ToolCallDelta(
            index: 0,
            id: 'call_123',
            name: 'get_weather',
            arguments: '{"city": "Oslo"}',
        );

        $this->assertInstanceOf(StreamEvent::class, $event);
        $this->assertSame(0, $event->index);
        $this->assertSame('call_123', $event->id);
        $this->assertSame('get_weather', $event->name);
        $this->assertSame('{"city": "Oslo"}', $event->arguments);
    }

    public function test_tool_call_delta_partial(): void
    {
        $event = new ToolCallDelta(index: 1, arguments: '{"partial":');

        $this->assertSame(1, $event->index);
        $this->assertNull($event->id);
        $this->assertNull($event->name);
        $this->assertSame('{"partial":', $event->arguments);
    }

    public function test_tool_calls_ready(): void
    {
        $toolCalls = [
            new ToolCall('call_1', 'func_a', ['x' => 1]),
            new ToolCall('call_2', 'func_b', ['y' => 2]),
        ];

        $event = new ToolCallsReady($toolCalls);

        $this->assertInstanceOf(StreamEvent::class, $event);
        $this->assertCount(2, $event->toolCalls);
        $this->assertSame('call_1', $event->toolCalls[0]->id);
        $this->assertSame('call_2', $event->toolCalls[1]->id);
    }

    public function test_stream_completed(): void
    {
        $event = new StreamCompleted(
            finishReason: 'stop',
            usage: new UsageInfo(10, 20, 30),
        );

        $this->assertInstanceOf(StreamEvent::class, $event);
        $this->assertSame('stop', $event->finishReason);
        $this->assertSame(10, $event->usage->inputTokens);
        $this->assertSame(20, $event->usage->outputTokens);
    }

    public function test_stream_completed_minimal(): void
    {
        $event = new StreamCompleted;

        $this->assertNull($event->finishReason);
        $this->assertNull($event->usage);
    }
}
