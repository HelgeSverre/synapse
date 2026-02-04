<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Parser;

use HelgeSverre\Synapse\Parser\NumberParser;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use PHPUnit\Framework\TestCase;

final class NumberParserTest extends TestCase
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

    public function test_parses_integer(): void
    {
        $parser = new NumberParser;
        $result = $parser->parse($this->createResponse('42'));

        $this->assertSame(42, $result);
    }

    public function test_parses_float(): void
    {
        $parser = new NumberParser;
        $result = $parser->parse($this->createResponse('3.14'));

        $this->assertSame(3.14, $result);
    }

    public function test_parses_negative_number(): void
    {
        $parser = new NumberParser;
        $result = $parser->parse($this->createResponse('-123'));

        $this->assertSame(-123, $result);
    }

    public function test_parses_number_with_commas(): void
    {
        $parser = new NumberParser;
        $result = $parser->parse($this->createResponse('1,234,567'));

        $this->assertSame(1234567, $result);
    }

    public function test_extracts_number_from_text(): void
    {
        $parser = new NumberParser;
        $result = $parser->parse($this->createResponse('The answer is 42'));

        $this->assertSame(42, $result);
    }

    public function test_extracts_first_number(): void
    {
        $parser = new NumberParser;
        $result = $parser->parse($this->createResponse('Between 10 and 20'));

        $this->assertSame(10, $result);
    }

    public function test_int_only_mode(): void
    {
        $parser = new NumberParser(intOnly: true);
        $result = $parser->parse($this->createResponse('3.14'));

        $this->assertSame(3, $result);
    }

    public function test_returns_zero_for_no_number(): void
    {
        $parser = new NumberParser;
        $result = $parser->parse($this->createResponse('no numbers here'));

        $this->assertSame(0, $result);
    }

    public function test_parses_decimal_with_commas(): void
    {
        $parser = new NumberParser;
        $result = $parser->parse($this->createResponse('1,234.56'));

        $this->assertSame(1234.56, $result);
    }
}
