<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Parser;

use LlmExe\Parser\ListToJsonParser;
use LlmExe\Provider\Response\GenerationResponse;
use PHPUnit\Framework\TestCase;

final class ListToJsonParserTest extends TestCase
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

    public function test_parses_simple_key_value(): void
    {
        $parser = new ListToJsonParser;
        $text = "name: Alice\nage: 30";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'name' => 'Alice',
            'age' => 30,
        ], $result);
    }

    public function test_parses_booleans(): void
    {
        $parser = new ListToJsonParser;
        $text = "active: true\ndeleted: false";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'active' => true,
            'deleted' => false,
        ], $result);
    }

    public function test_parses_null(): void
    {
        $parser = new ListToJsonParser;
        $text = 'value: null';
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['value' => null], $result);
    }

    public function test_parses_floats(): void
    {
        $parser = new ListToJsonParser;
        $text = 'price: 19.99';
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['price' => 19.99], $result);
    }

    public function test_parses_array_notation(): void
    {
        $parser = new ListToJsonParser;
        $text = 'colors: [red, green, blue]';
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['colors' => ['red', 'green', 'blue']], $result);
    }

    public function test_parses_nested_structure(): void
    {
        $parser = new ListToJsonParser;
        $text = "user:\n  name: Bob\n  age: 25";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'user' => [
                'name' => 'Bob',
                'age' => 25,
            ],
        ], $result);
    }

    public function test_parses_deep_nesting(): void
    {
        $parser = new ListToJsonParser;
        $text = "level1:\n  level2:\n    level3: deep";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'level1' => [
                'level2' => [
                    'level3' => 'deep',
                ],
            ],
        ], $result);
    }

    public function test_custom_separator(): void
    {
        $parser = new ListToJsonParser(separator: '=');
        $text = "name=Alice\nage=30";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'name' => 'Alice',
            'age' => 30,
        ], $result);
    }

    public function test_handles_empty_input(): void
    {
        $parser = new ListToJsonParser;
        $result = $parser->parse($this->createResponse(''));

        $this->assertSame([], $result);
    }

    public function test_handles_mixed_content(): void
    {
        $parser = new ListToJsonParser;
        $text = "title: My App\nversion: 1.0\nfeatures:\n  - auth: true\n  - api: true";
        $result = $parser->parse($this->createResponse($text));

        $this->assertArrayHasKey('title', $result);
        $this->assertSame('My App', $result['title']);
        $this->assertSame(1.0, $result['version']);
    }
}
