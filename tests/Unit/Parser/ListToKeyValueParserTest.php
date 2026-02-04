<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Parser;

use HelgeSverre\Synapse\Parser\ListToKeyValueParser;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use PHPUnit\Framework\TestCase;

final class ListToKeyValueParserTest extends TestCase
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
        $parser = new ListToKeyValueParser;
        $text = "Name: Alice\nAge: 30\nCity: NYC";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'Name' => 'Alice',
            'Age' => '30',
            'City' => 'NYC',
        ], $result);
    }

    public function test_parses_with_list_markers(): void
    {
        $parser = new ListToKeyValueParser;
        $text = "1. Name: Bob\n2. Email: bob@example.com";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'Name' => 'Bob',
            'Email' => 'bob@example.com',
        ], $result);
    }

    public function test_parses_with_dash_markers(): void
    {
        $parser = new ListToKeyValueParser;
        $text = "- Key: Value\n- Another: Data";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'Key' => 'Value',
            'Another' => 'Data',
        ], $result);
    }

    public function test_custom_separator(): void
    {
        $parser = new ListToKeyValueParser(separator: '=');
        $text = "name=Alice\nage=30";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'name' => 'Alice',
            'age' => '30',
        ], $result);
    }

    public function test_trims_values(): void
    {
        $parser = new ListToKeyValueParser;
        $text = '  Key  :  Value with spaces  ';
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['Key' => 'Value with spaces'], $result);
    }

    public function test_no_trim_values(): void
    {
        $parser = new ListToKeyValueParser(trimValues: false);
        $text = "Key:  Value\nAnother:  Spaced  ";
        $result = $parser->parse($this->createResponse($text));

        // Values preserve leading/trailing spacing when trimValues is false
        $this->assertSame([
            'Key' => '  Value',
            'Another' => '  Spaced  ',
        ], $result);
    }

    public function test_skips_lines_without_separator(): void
    {
        $parser = new ListToKeyValueParser;
        $text = "Name: Alice\nJust a line\nAge: 30";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame([
            'Name' => 'Alice',
            'Age' => '30',
        ], $result);
    }

    public function test_handles_value_with_separator(): void
    {
        $parser = new ListToKeyValueParser;
        $text = 'URL: https://example.com';
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame(['URL' => 'https://example.com'], $result);
    }

    public function test_handles_empty_input(): void
    {
        $parser = new ListToKeyValueParser;
        $result = $parser->parse($this->createResponse(''));

        $this->assertSame([], $result);
    }
}
