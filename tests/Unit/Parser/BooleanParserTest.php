<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Parser;

use LlmExe\Parser\BooleanParser;
use LlmExe\Provider\Response\GenerationResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BooleanParserTest extends TestCase
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

    #[DataProvider('trueValuesProvider')]
    public function test_parses_true(string $input): void
    {
        $parser = new BooleanParser;
        $result = $parser->parse($this->createResponse($input));

        $this->assertTrue($result);
    }

    public static function trueValuesProvider(): array
    {
        return [
            ['yes'],
            ['YES'],
            ['Yes'],
            ['true'],
            ['TRUE'],
            ['True'],
            ['1'],
            ['correct'],
            ['affirmative'],
            ['  yes  '],
            ['The answer is yes.'],
        ];
    }

    #[DataProvider('falseValuesProvider')]
    public function test_parses_false(string $input): void
    {
        $parser = new BooleanParser;
        $result = $parser->parse($this->createResponse($input));

        $this->assertFalse($result);
    }

    public static function falseValuesProvider(): array
    {
        return [
            ['no'],
            ['NO'],
            ['No'],
            ['false'],
            ['FALSE'],
            ['False'],
            ['0'],
            ['incorrect'],
            ['negative'],
            ['  no  '],
            ['The answer is no.'],
        ];
    }

    public function test_defaults_to_false_for_unknown(): void
    {
        $parser = new BooleanParser;
        $result = $parser->parse($this->createResponse('maybe'));

        $this->assertFalse($result);
    }

    public function test_trim_is_required_for_exact_match(): void
    {
        $parser = new BooleanParser;

        $this->assertTrue($parser->parse($this->createResponse("\t\nyes\t\n")));
        $this->assertFalse($parser->parse($this->createResponse("\t\nno\t\n")));
    }

    public function test_strtolower_is_required_for_exact_match(): void
    {
        $parser = new BooleanParser;

        $this->assertTrue($parser->parse($this->createResponse('YES')));
        $this->assertTrue($parser->parse($this->createResponse('TRUE')));
        $this->assertFalse($parser->parse($this->createResponse('NO')));
        $this->assertFalse($parser->parse($this->createResponse('FALSE')));
    }

    public function test_contains_match_finds_true_in_longer_text(): void
    {
        $parser = new BooleanParser;

        $this->assertTrue($parser->parse($this->createResponse('I think the answer is yes definitely')));
        $this->assertTrue($parser->parse($this->createResponse('This is true based on evidence')));
        $this->assertTrue($parser->parse($this->createResponse('That is correct!')));
        $this->assertTrue($parser->parse($this->createResponse('Response: affirmative')));
    }

    public function test_contains_match_finds_false_in_longer_text(): void
    {
        $parser = new BooleanParser;

        $this->assertFalse($parser->parse($this->createResponse('no way at all')));
        $this->assertFalse($parser->parse($this->createResponse('that seems false to me')));
        $this->assertFalse($parser->parse($this->createResponse('that seems wrong!')));
        $this->assertFalse($parser->parse($this->createResponse('my response: negative')));
    }

    public function test_exact_match_takes_precedence_over_contains_match(): void
    {
        $parser = new BooleanParser;

        $this->assertTrue($parser->parse($this->createResponse('yes')));
        $this->assertFalse($parser->parse($this->createResponse('no')));
    }

    public function test_true_contains_match_checked_before_false(): void
    {
        $parser = new BooleanParser;

        $this->assertTrue($parser->parse($this->createResponse('yes and no both apply')));
    }
}
