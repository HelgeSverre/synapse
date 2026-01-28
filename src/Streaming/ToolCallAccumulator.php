<?php

declare(strict_types=1);

namespace LlmExe\Streaming;

use LlmExe\Provider\Request\ToolCall;

/**
 * Accumulates partial tool call deltas into complete ToolCall objects.
 *
 * Tool calls stream incrementally: ID first, then name, then arguments as JSON fragments.
 * Multiple tool calls can be in flight simultaneously (tracked by index).
 */
final class ToolCallAccumulator
{
    /** @var array<int, array{id: string, name: string, arguments: string}> */
    private array $calls = [];

    /**
     * Add a tool call delta to the accumulator.
     */
    public function add(ToolCallDelta $delta): void
    {
        $index = $delta->index;

        if (! isset($this->calls[$index])) {
            $this->calls[$index] = ['id' => '', 'name' => '', 'arguments' => ''];
        }

        if ($delta->id !== null) {
            $this->calls[$index]['id'] = $delta->id;
        }

        if ($delta->name !== null) {
            $this->calls[$index]['name'] = $delta->name;
        }

        if ($delta->arguments !== null) {
            $this->calls[$index]['arguments'] .= $delta->arguments;
        }
    }

    /**
     * Check if any tool calls have been accumulated.
     */
    public function hasToolCalls(): bool
    {
        return $this->calls !== [];
    }

    /**
     * Get the number of tool calls accumulated.
     */
    public function count(): int
    {
        return count($this->calls);
    }

    /**
     * Finalize and return all accumulated tool calls.
     *
     * @return list<ToolCall>
     *
     * @throws \JsonException If arguments JSON is invalid
     */
    public function getToolCalls(): array
    {
        $result = [];

        foreach ($this->calls as $call) {
            $arguments = $call['arguments'] !== ''
                ? json_decode($call['arguments'], true, 512, JSON_THROW_ON_ERROR)
                : [];

            $result[] = new ToolCall(
                id: $call['id'],
                name: $call['name'],
                arguments: is_array($arguments) ? $arguments : [],
            );
        }

        return $result;
    }

    /**
     * Clear all accumulated tool calls.
     */
    public function clear(): void
    {
        $this->calls = [];
    }

    /**
     * Get raw accumulated data (useful for debugging).
     *
     * @return array<int, array{id: string, name: string, arguments: string}>
     */
    public function getRawCalls(): array
    {
        return $this->calls;
    }
}
