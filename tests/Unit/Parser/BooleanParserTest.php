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
}
