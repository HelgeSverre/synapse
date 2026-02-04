<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Parser;

use HelgeSverre\Synapse\Parser\StringParser;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use PHPUnit\Framework\TestCase;

final class StringParserTest extends TestCase
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

    public function test_parses_string_with_trim(): void
    {
        $parser = new StringParser;
        $result = $parser->parse($this->createResponse('  Hello World  '));

        $this->assertSame('Hello World', $result);
    }

    public function test_parses_string_without_trim(): void
    {
        $parser = new StringParser(trim: false);
        $result = $parser->parse($this->createResponse('  Hello World  '));

        $this->assertSame('  Hello World  ', $result);
    }

    public function test_parses_empty_string(): void
    {
        $parser = new StringParser;
        $result = $parser->parse($this->createResponse(''));

        $this->assertSame('', $result);
    }

    public function test_parses_multiline_string(): void
    {
        $parser = new StringParser;
        $result = $parser->parse($this->createResponse("  Line 1\nLine 2  "));

        $this->assertSame("Line 1\nLine 2", $result);
    }
}
