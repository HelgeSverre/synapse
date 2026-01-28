<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Parser;

use LlmExe\Parser\EnumParser;
use LlmExe\Provider\Response\GenerationResponse;
use PHPUnit\Framework\TestCase;

final class EnumParserTest extends TestCase
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

    public function test_matches_exact_value(): void
    {
        $parser = new EnumParser(['low', 'medium', 'high']);
        $result = $parser->parse($this->createResponse('medium'));

        $this->assertSame('medium', $result);
    }

    public function test_matches_case_insensitive(): void
    {
        $parser = new EnumParser(['low', 'medium', 'high']);
        $result = $parser->parse($this->createResponse('MEDIUM'));

        $this->assertSame('medium', $result);
    }

    public function test_matches_case_sensitive(): void
    {
        $parser = new EnumParser(['Low', 'Medium', 'High'], caseSensitive: true);

        $this->assertSame('Medium', $parser->parse($this->createResponse('Medium')));
        $this->assertNull($parser->parse($this->createResponse('medium')));
    }

    public function test_extracts_from_sentence(): void
    {
        $parser = new EnumParser(['low', 'medium', 'high']);
        $result = $parser->parse($this->createResponse('The priority is high.'));

        $this->assertSame('high', $result);
    }

    public function test_returns_null_for_no_match(): void
    {
        $parser = new EnumParser(['low', 'medium', 'high']);
        $result = $parser->parse($this->createResponse('unknown'));

        $this->assertNull($result);
    }

    public function test_matches_first_occurrence(): void
    {
        $parser = new EnumParser(['low', 'medium', 'high']);
        $result = $parser->parse($this->createResponse('low to high'));

        $this->assertSame('low', $result);
    }

    public function test_trims_input(): void
    {
        $parser = new EnumParser(['yes', 'no']);
        $result = $parser->parse($this->createResponse('  yes  '));

        $this->assertSame('yes', $result);
    }

    public function test_handles_empty_allowed_values(): void
    {
        $parser = new EnumParser([]);
        $result = $parser->parse($this->createResponse('anything'));

        $this->assertNull($result);
    }
}
