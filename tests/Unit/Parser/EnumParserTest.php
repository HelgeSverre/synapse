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

    public function test_trim_is_required_for_match(): void
    {
        $parser = new EnumParser(['apple', 'banana']);

        $this->assertSame('apple', $parser->parse($this->createResponse("\t\napple\t\n")));
        $this->assertSame('banana', $parser->parse($this->createResponse('   banana   ')));
    }

    public function test_case_insensitive_exact_match(): void
    {
        $parser = new EnumParser(['Apple', 'Banana']);

        $this->assertSame('Apple', $parser->parse($this->createResponse('apple')));
        $this->assertSame('Apple', $parser->parse($this->createResponse('APPLE')));
        $this->assertSame('Banana', $parser->parse($this->createResponse('BaNaNa')));
    }

    public function test_case_sensitive_no_match(): void
    {
        $parser = new EnumParser(['Apple', 'Banana'], caseSensitive: true);

        $this->assertNull($parser->parse($this->createResponse('apple')));
        $this->assertNull($parser->parse($this->createResponse('BANANA')));
    }

    public function test_case_sensitive_contains_match(): void
    {
        $parser = new EnumParser(['Apple', 'Banana'], caseSensitive: true);

        $this->assertSame('Apple', $parser->parse($this->createResponse('I want an Apple please')));
        $this->assertNull($parser->parse($this->createResponse('I want an apple please')));
    }

    public function test_case_insensitive_contains_match(): void
    {
        $parser = new EnumParser(['apple', 'banana']);

        $this->assertSame('apple', $parser->parse($this->createResponse('I would like an APPLE')));
        $this->assertSame('banana', $parser->parse($this->createResponse('Give me a BANANA')));
    }

    public function test_stripos_used_for_case_insensitive_contains(): void
    {
        $parser = new EnumParser(['yes', 'no']);

        $this->assertSame('yes', $parser->parse($this->createResponse('The answer is YES!')));
        $this->assertSame('no', $parser->parse($this->createResponse('I say NO to that')));
    }

    public function test_str_contains_used_for_case_sensitive_contains(): void
    {
        $parser = new EnumParser(['Yes', 'No'], caseSensitive: true);

        $this->assertSame('Yes', $parser->parse($this->createResponse('The answer is Yes!')));
        $this->assertNull($parser->parse($this->createResponse('The answer is YES!')));
    }
}
