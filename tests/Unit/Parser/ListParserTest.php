<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Parser;

use HelgeSverre\Synapse\Parser\ListParser;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use PHPUnit\Framework\TestCase;

final class ListParserTest extends TestCase
{
    private function createResponse(string $text): GenerationResponse
    {
        return new GenerationResponse(
            text: $text,
            messages: [],
            toolCalls: [],
            model: 'test',
        );
    }

    public function test_parses_numbered_list(): void
    {
        $parser = new ListParser;
        $text = "1. First item\n2. Second item\n3. Third item";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['First item', 'Second item', 'Third item'], $result);
    }

    public function test_parses_numbered_list_with_parentheses(): void
    {
        $parser = new ListParser;
        $text = "1) Apple\n2) Banana\n3) Cherry";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['Apple', 'Banana', 'Cherry'], $result);
    }

    public function test_parses_dash_list(): void
    {
        $parser = new ListParser;
        $text = "- Item A\n- Item B\n- Item C";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['Item A', 'Item B', 'Item C'], $result);
    }

    public function test_parses_asterisk_list(): void
    {
        $parser = new ListParser;
        $text = "* Red\n* Green\n* Blue";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['Red', 'Green', 'Blue'], $result);
    }

    public function test_parses_bullet_list(): void
    {
        $parser = new ListParser;
        $text = "• Alpha\n• Beta\n• Gamma";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $result);
    }

    public function test_skips_empty_lines(): void
    {
        $parser = new ListParser;
        $text = "1. First\n\n2. Second\n\n3. Third";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['First', 'Second', 'Third'], $result);
    }

    public function test_trims_items(): void
    {
        $parser = new ListParser;
        $text = "1.   Padded item   \n2. Normal item";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['Padded item', 'Normal item'], $result);
    }

    public function test_handles_empty_input(): void
    {
        $parser = new ListParser;
        $result = $parser->parse($this->createResponse(''));

        $this->assertSame([], $result);
    }

    public function test_handles_mixed_markers(): void
    {
        $parser = new ListParser;
        $text = "1. First\n- Second\n* Third";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['First', 'Second', 'Third'], $result);
    }

    public function test_items_with_extra_whitespace_on_lines(): void
    {
        $parser = new ListParser;
        $text = "   1. First item   \n   2. Second item   ";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['First item', 'Second item'], $result);
    }

    public function test_multiple_empty_lines_between_items(): void
    {
        $parser = new ListParser;
        $text = "1. First\n\n\n\n2. Second\n\n\n3. Third";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['First', 'Second', 'Third'], $result);
    }

    public function test_line_becomes_empty_after_marker_removal(): void
    {
        $parser = new ListParser;
        $text = "1. \n2. Actual item\n- \n* Another";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['Actual item', 'Another'], $result);
    }

    public function test_handles_windows_line_endings(): void
    {
        $parser = new ListParser;
        $text = "1. First\r\n2. Second\r\n3. Third";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['First', 'Second', 'Third'], $result);
    }

    public function test_handles_only_whitespace_input(): void
    {
        $parser = new ListParser;
        $result = $parser->parse($this->createResponse("   \n   \n   "));

        $this->assertSame([], $result);
    }

    public function test_trim_outer_text_is_required(): void
    {
        $parser = new ListParser;
        $text = "\n\n1. First\n2. Second\n\n";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['First', 'Second'], $result);
    }

    public function test_preg_replace_returns_null_handled(): void
    {
        $parser = new ListParser;
        $text = '1. Item with special chars: test';
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['Item with special chars: test'], $result);
    }
}
