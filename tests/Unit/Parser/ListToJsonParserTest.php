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

    public function test_multiple_nesting_levels(): void
    {
        $parser = new ListToJsonParser;
        $text = "a:\n  b:\n    c:\n      d: deep value";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'a' => [
                'b' => [
                    'c' => [
                        'd' => 'deep value',
                    ],
                ],
            ],
        ], $result);
    }

    public function test_empty_value_becomes_null(): void
    {
        $parser = new ListToJsonParser;
        $text = "name:\nage: 30";
        $result = $parser->parse($this->createResponse($text));

        $this->assertArrayHasKey('name', $result);
        $this->assertNull($result['name']);
        $this->assertSame(30, $result['age']);
    }

    public function test_indent_detection_with_spaces(): void
    {
        $parser = new ListToJsonParser(indentSpaces: 4);
        $text = "parent:\n    child: value";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'parent' => [
                'child' => 'value',
            ],
        ], $result);
    }

    public function test_get_indent_level_calculation(): void
    {
        $parser = new ListToJsonParser;
        $text = "root:\n  child1: a\n  child2: b";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'root' => [
                'child1' => 'a',
                'child2' => 'b',
            ],
        ], $result);
    }

    public function test_sibling_nesting_levels(): void
    {
        $parser = new ListToJsonParser;
        $text = "first:\n  nested: 1\nsecond:\n  nested: 2";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'first' => ['nested' => 1],
            'second' => ['nested' => 2],
        ], $result);
    }

    public function test_line_with_less_indent_breaks_nesting(): void
    {
        $parser = new ListToJsonParser;
        $text = "outer:\n  inner: value\nnext: item";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'outer' => ['inner' => 'value'],
            'next' => 'item',
        ], $result);
    }

    public function test_empty_lines_in_nested_structure(): void
    {
        $parser = new ListToJsonParser;
        $text = "parent:\n  child1: a\n\n  child2: b";
        $result = $parser->parse($this->createResponse($text));

        $this->assertArrayHasKey('parent', $result);
    }

    public function test_key_only_with_no_children(): void
    {
        $parser = new ListToJsonParser;
        $text = 'lonely:';
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['lonely' => null], $result);
    }

    public function test_array_notation_with_spaces(): void
    {
        $parser = new ListToJsonParser;
        $text = 'items: [one, two, three]';
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['items' => ['one', 'two', 'three']], $result);
    }

    public function test_numeric_string_detection(): void
    {
        $parser = new ListToJsonParser;
        $text = "int: 42\nfloat: 3.14\nstring: not a number";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(42, $result['int']);
        $this->assertSame(3.14, $result['float']);
        $this->assertSame('not a number', $result['string']);
    }

    public function test_list_markers_removed_in_nested(): void
    {
        $parser = new ListToJsonParser;
        $text = "items:\n  - first: 1\n  - second: 2";
        $result = $parser->parse($this->createResponse($text));

        $this->assertArrayHasKey('items', $result);
    }

    public function test_trim_preserves_structure(): void
    {
        $parser = new ListToJsonParser;
        $text = "\n\nname: Alice\nage: 30\n\n";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'name' => 'Alice',
            'age' => 30,
        ], $result);
    }

    public function test_while_loop_processes_all_lines(): void
    {
        $parser = new ListToJsonParser;
        $text = "a: 1\nb: 2\nc: 3\nd: 4\ne: 5";
        $result = $parser->parse($this->createResponse($text));

        $this->assertCount(5, $result);
        $this->assertSame(1, $result['a']);
        $this->assertSame(5, $result['e']);
    }
}
