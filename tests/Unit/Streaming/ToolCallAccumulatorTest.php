<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Streaming;

use LlmExe\Streaming\ToolCallAccumulator;
use LlmExe\Streaming\ToolCallDelta;
use PHPUnit\Framework\TestCase;

final class ToolCallAccumulatorTest extends TestCase
{
    public function test_empty_accumulator(): void
    {
        $acc = new ToolCallAccumulator;

        $this->assertFalse($acc->hasToolCalls());
        $this->assertSame(0, $acc->count());
        $this->assertSame([], $acc->getToolCalls());
    }

    public function test_accumulate_single_tool_call(): void
    {
        $acc = new ToolCallAccumulator;

        $acc->add(new ToolCallDelta(index: 0, id: 'call_123'));
        $acc->add(new ToolCallDelta(index: 0, name: 'get_weather'));
        $acc->add(new ToolCallDelta(index: 0, arguments: '{"city":'));
        $acc->add(new ToolCallDelta(index: 0, arguments: '"Oslo"}'));

        $this->assertTrue($acc->hasToolCalls());
        $this->assertSame(1, $acc->count());

        $calls = $acc->getToolCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('call_123', $calls[0]->id);
        $this->assertSame('get_weather', $calls[0]->name);
        $this->assertSame(['city' => 'Oslo'], $calls[0]->arguments);
    }

    public function test_accumulate_multiple_tool_calls(): void
    {
        $acc = new ToolCallAccumulator;

        // First tool call
        $acc->add(new ToolCallDelta(index: 0, id: 'call_1', name: 'func_a'));
        $acc->add(new ToolCallDelta(index: 0, arguments: '{"x": 1}'));

        // Second tool call (interleaved)
        $acc->add(new ToolCallDelta(index: 1, id: 'call_2', name: 'func_b'));
        $acc->add(new ToolCallDelta(index: 1, arguments: '{"y": 2}'));

        $this->assertSame(2, $acc->count());

        $calls = $acc->getToolCalls();
        $this->assertSame('call_1', $calls[0]->id);
        $this->assertSame('func_a', $calls[0]->name);
        $this->assertSame(['x' => 1], $calls[0]->arguments);

        $this->assertSame('call_2', $calls[1]->id);
        $this->assertSame('func_b', $calls[1]->name);
        $this->assertSame(['y' => 2], $calls[1]->arguments);
    }

    public function test_accumulate_fragmented_arguments(): void
    {
        $acc = new ToolCallAccumulator;

        $acc->add(new ToolCallDelta(index: 0, id: 'call_1', name: 'search'));
        $acc->add(new ToolCallDelta(index: 0, arguments: '{'));
        $acc->add(new ToolCallDelta(index: 0, arguments: '"query"'));
        $acc->add(new ToolCallDelta(index: 0, arguments: ':'));
        $acc->add(new ToolCallDelta(index: 0, arguments: '"hello world"'));
        $acc->add(new ToolCallDelta(index: 0, arguments: '}'));

        $calls = $acc->getToolCalls();
        $this->assertSame(['query' => 'hello world'], $calls[0]->arguments);
    }

    public function test_empty_arguments(): void
    {
        $acc = new ToolCallAccumulator;

        $acc->add(new ToolCallDelta(index: 0, id: 'call_1', name: 'no_args'));

        $calls = $acc->getToolCalls();
        $this->assertSame([], $calls[0]->arguments);
    }

    public function test_clear(): void
    {
        $acc = new ToolCallAccumulator;

        $acc->add(new ToolCallDelta(index: 0, id: 'call_1', name: 'test'));

        $this->assertTrue($acc->hasToolCalls());

        $acc->clear();

        $this->assertFalse($acc->hasToolCalls());
        $this->assertSame(0, $acc->count());
    }

    public function test_get_raw_calls(): void
    {
        $acc = new ToolCallAccumulator;

        $acc->add(new ToolCallDelta(index: 0, id: 'call_1', name: 'test'));
        $acc->add(new ToolCallDelta(index: 0, arguments: '{"partial":'));

        $raw = $acc->getRawCalls();

        $this->assertSame([
            0 => ['id' => 'call_1', 'name' => 'test', 'arguments' => '{"partial":'],
        ], $raw);
    }

    public function test_throws_on_invalid_json(): void
    {
        $acc = new ToolCallAccumulator;

        $acc->add(new ToolCallDelta(index: 0, id: 'call_1', name: 'test'));
        $acc->add(new ToolCallDelta(index: 0, arguments: 'not valid json'));

        $this->expectException(\JsonException::class);
        $acc->getToolCalls();
    }

    public function test_handles_non_array_json(): void
    {
        $acc = new ToolCallAccumulator;

        $acc->add(new ToolCallDelta(index: 0, id: 'call_1', name: 'test'));
        $acc->add(new ToolCallDelta(index: 0, arguments: '"just a string"'));

        $calls = $acc->getToolCalls();
        $this->assertSame([], $calls[0]->arguments);
    }

    public function test_openai_style_deltas(): void
    {
        $acc = new ToolCallAccumulator;

        // OpenAI sends tool calls like this
        $acc->add(new ToolCallDelta(
            index: 0,
            id: 'call_abc123',
            name: 'get_current_weather',
            arguments: '',
        ));
        $acc->add(new ToolCallDelta(index: 0, arguments: '{"'));
        $acc->add(new ToolCallDelta(index: 0, arguments: 'location'));
        $acc->add(new ToolCallDelta(index: 0, arguments: '":"'));
        $acc->add(new ToolCallDelta(index: 0, arguments: 'Boston'));
        $acc->add(new ToolCallDelta(index: 0, arguments: '"}'));

        $calls = $acc->getToolCalls();
        $this->assertSame('call_abc123', $calls[0]->id);
        $this->assertSame('get_current_weather', $calls[0]->name);
        $this->assertSame(['location' => 'Boston'], $calls[0]->arguments);
    }
}
