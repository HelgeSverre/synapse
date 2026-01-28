<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Parser;

use LlmExe\Parser\JsonParser;
use LlmExe\Provider\Response\GenerationResponse;
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
}
