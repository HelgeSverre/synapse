<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Streaming;

use LlmExe\Streaming\SseParser;
use PHPUnit\Framework\TestCase;

final class SseParserTest extends TestCase
{
    public function test_parse_simple_data_event(): void
    {
        $lines = [
            "data: {\"text\": \"Hello\"}\n",
            "\n",
        ];

        $events = iterator_to_array(SseParser::parse($lines));

        $this->assertCount(1, $events);
        $this->assertNull($events[0]['event']);
        $this->assertSame('{"text": "Hello"}', $events[0]['data']);
    }

    public function test_parse_event_with_type(): void
    {
        $lines = [
            "event: message_start\n",
            "data: {\"type\": \"message_start\"}\n",
            "\n",
        ];

        $events = iterator_to_array(SseParser::parse($lines));

        $this->assertCount(1, $events);
        $this->assertSame('message_start', $events[0]['event']);
        $this->assertSame('{"type": "message_start"}', $events[0]['data']);
    }

    public function test_parse_multiple_events(): void
    {
        $lines = [
            "data: {\"delta\": \"Hello\"}\n",
            "\n",
            "data: {\"delta\": \" world\"}\n",
            "\n",
            "data: [DONE]\n",
            "\n",
        ];

        $events = iterator_to_array(SseParser::parse($lines));

        $this->assertCount(3, $events);
        $this->assertSame('{"delta": "Hello"}', $events[0]['data']);
        $this->assertSame('{"delta": " world"}', $events[1]['data']);
        $this->assertSame('[DONE]', $events[2]['data']);
    }

    public function test_parse_multiline_data(): void
    {
        $lines = [
            "data: line1\n",
            "data: line2\n",
            "data: line3\n",
            "\n",
        ];

        $events = iterator_to_array(SseParser::parse($lines));

        $this->assertCount(1, $events);
        $this->assertSame("line1\nline2\nline3", $events[0]['data']);
    }

    public function test_parse_ignores_comments(): void
    {
        $lines = [
            ": this is a comment\n",
            "data: actual data\n",
            ": another comment\n",
            "\n",
        ];

        $events = iterator_to_array(SseParser::parse($lines));

        $this->assertCount(1, $events);
        $this->assertSame('actual data', $events[0]['data']);
    }

    public function test_parse_handles_carriage_return(): void
    {
        $lines = [
            "data: content\r\n",
            "\r\n",
        ];

        $events = iterator_to_array(SseParser::parse($lines));

        $this->assertCount(1, $events);
        $this->assertSame('content', $events[0]['data']);
    }

    public function test_parse_removes_single_leading_space(): void
    {
        $lines = [
            "data: has leading space\n",
            "\n",
        ];

        $events = iterator_to_array(SseParser::parse($lines));

        $this->assertSame('has leading space', $events[0]['data']);
    }

    public function test_parse_preserves_multiple_leading_spaces(): void
    {
        $lines = [
            "data:  two spaces\n",
            "\n",
        ];

        $events = iterator_to_array(SseParser::parse($lines));

        // Only first space removed per SSE spec
        $this->assertSame(' two spaces', $events[0]['data']);
    }

    public function test_parse_handles_empty_data(): void
    {
        $lines = [
            "data:\n",
            "\n",
        ];

        $events = iterator_to_array(SseParser::parse($lines));

        $this->assertCount(1, $events);
        $this->assertSame('', $events[0]['data']);
    }

    public function test_parse_ignores_unknown_fields(): void
    {
        $lines = [
            "unknown: ignored\n",
            "data: kept\n",
            "retry: 3000\n",
            "\n",
        ];

        $events = iterator_to_array(SseParser::parse($lines));

        $this->assertCount(1, $events);
        $this->assertSame('kept', $events[0]['data']);
    }

    public function test_parse_yields_remaining_event_at_end(): void
    {
        $lines = [
            "data: no trailing blank line\n",
        ];

        $events = iterator_to_array(SseParser::parse($lines));

        $this->assertCount(1, $events);
        $this->assertSame('no trailing blank line', $events[0]['data']);
    }

    public function test_parse_skips_empty_events(): void
    {
        $lines = [
            "\n",
            "\n",
            "data: actual\n",
            "\n",
            "\n",
        ];

        $events = iterator_to_array(SseParser::parse($lines));

        $this->assertCount(1, $events);
        $this->assertSame('actual', $events[0]['data']);
    }

    public function test_parse_anthropic_format(): void
    {
        $lines = [
            "event: content_block_delta\n",
            "data: {\"type\":\"content_block_delta\",\"index\":0,\"delta\":{\"type\":\"text_delta\",\"text\":\"Hello\"}}\n",
            "\n",
            "event: message_stop\n",
            "data: {\"type\":\"message_stop\"}\n",
            "\n",
        ];

        $events = iterator_to_array(SseParser::parse($lines));

        $this->assertCount(2, $events);
        $this->assertSame('content_block_delta', $events[0]['event']);
        $this->assertSame('message_stop', $events[1]['event']);
    }

    public function test_parse_openai_format(): void
    {
        $lines = [
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"delta\":{\"content\":\"Hi\"}}]}\n",
            "\n",
            "data: {\"id\":\"chatcmpl-123\",\"choices\":[{\"delta\":{\"content\":\"!\"}}]}\n",
            "\n",
            "data: [DONE]\n",
            "\n",
        ];

        $events = iterator_to_array(SseParser::parse($lines));

        $this->assertCount(3, $events);
        $this->assertNull($events[0]['event']);
        $this->assertStringContainsString('"content":"Hi"', $events[0]['data']);
        $this->assertSame('[DONE]', $events[2]['data']);
    }
}
