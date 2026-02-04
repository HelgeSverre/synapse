<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Parser;

use HelgeSverre\Synapse\Parser\JsonParser;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use PHPUnit\Framework\TestCase;

final class JsonParserTest extends TestCase
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

    public function test_parses_simple_object(): void
    {
        $parser = new JsonParser;
        $result = $parser->parse($this->createResponse('{"name": "Alice", "age": 30}'));

        $this->assertSame(['name' => 'Alice', 'age' => 30], $result);
    }

    public function test_parses_array(): void
    {
        $parser = new JsonParser;
        $result = $parser->parse($this->createResponse('[1, 2, 3]'));

        $this->assertSame([1, 2, 3], $result);
    }

    public function test_parses_nested_object(): void
    {
        $parser = new JsonParser;
        $json = '{"user": {"name": "Bob", "address": {"city": "NYC"}}}';
        $result = $parser->parse($this->createResponse($json));

        $this->assertSame([
            'user' => [
                'name' => 'Bob',
                'address' => ['city' => 'NYC'],
            ],
        ], $result);
    }

    public function test_extracts_json_from_code_block(): void
    {
        $parser = new JsonParser;
        $text = "Here is the data:\n```json\n{\"name\": \"Charlie\"}\n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['name' => 'Charlie'], $result);
    }

    public function test_extracts_json_from_generic_code_block(): void
    {
        $parser = new JsonParser;
        $text = "```\n{\"key\": \"value\"}\n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['key' => 'value'], $result);
    }

    public function test_throws_on_invalid_json(): void
    {
        $this->expectException(\JsonException::class);

        $parser = new JsonParser;
        $parser->parse($this->createResponse('not valid json'));
    }

    public function test_throws_on_non_array_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not an object/array');

        $parser = new JsonParser;
        $parser->parse($this->createResponse('"just a string"'));
    }

    public function test_parses_with_whitespace(): void
    {
        $parser = new JsonParser;
        $result = $parser->parse($this->createResponse('  {"trimmed": true}  '));

        $this->assertSame(['trimmed' => true], $result);
    }

    public function test_schema_is_stored(): void
    {
        $schema = ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]];
        $parser = new JsonParser(schema: $schema);

        $this->assertSame($schema, $parser->getSchema());
    }

    public function test_extracts_json_from_surrounding_markdown(): void
    {
        $parser = new JsonParser;
        $text = "Here is the JSON data you requested:\n```json\n{\"name\": \"Test\"}\n```\nLet me know if you need anything else.";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['name' => 'Test'], $result);
    }

    public function test_extracts_json_array_from_code_block(): void
    {
        $parser = new JsonParser;
        $text = "```json\n[\"a\", \"b\", \"c\"]\n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['a', 'b', 'c'], $result);
    }

    public function test_regex_extraction_with_newlines_in_json(): void
    {
        $parser = new JsonParser;
        $text = "```json\n{\n  \"key\": \"value\",\n  \"nested\": {\n    \"inner\": true\n  }\n}\n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'key' => 'value',
            'nested' => ['inner' => true],
        ], $result);
    }

    public function test_validates_schema_when_enabled(): void
    {
        $schema = ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]];
        $parser = new JsonParser(schema: $schema, validateSchema: true);

        $result = $parser->parse($this->createResponse('{"name": "Alice"}'));
        $this->assertSame(['name' => 'Alice'], $result);
    }

    public function test_validates_schema_with_null_validator_passes(): void
    {
        $schema = ['type' => 'object', 'required' => ['name']];
        $parser = new JsonParser(schema: $schema, validateSchema: true);

        $result = $parser->parse($this->createResponse('{"other": "field"}'));
        $this->assertSame(['other' => 'field'], $result);
    }

    public function test_trim_is_required_before_parsing(): void
    {
        $parser = new JsonParser;
        $result = $parser->parse($this->createResponse("\n\n  {\"key\": \"value\"}  \n\n"));

        $this->assertSame(['key' => 'value'], $result);
    }

    public function test_code_block_without_json_language_tag(): void
    {
        $parser = new JsonParser;
        $text = "Some text before\n```\n{\"extracted\": true}\n```\nSome text after";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['extracted' => true], $result);
    }

    public function test_handles_code_block_with_minimal_newlines(): void
    {
        $parser = new JsonParser;
        $text = "```json\n{\"compact\":true}\n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['compact' => true], $result);
    }
}
