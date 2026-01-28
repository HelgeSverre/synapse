<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Parser;

use LlmExe\Parser\LlmFunctionParser;
use LlmExe\Parser\ParserTarget;
use LlmExe\Parser\StringParser;
use LlmExe\Provider\Request\ToolCall;
use LlmExe\Provider\Response\GenerationResponse;
use PHPUnit\Framework\TestCase;

final class LlmFunctionParserTest extends TestCase
{
    public function test_returns_tool_calls_when_present(): void
    {
        $wrappedParser = new StringParser;
        $parser = new LlmFunctionParser($wrappedParser);

        $toolCalls = [
            new ToolCall('call_1', 'get_weather', ['location' => 'NYC']),
            new ToolCall('call_2', 'get_time', ['timezone' => 'UTC']),
        ];

        $response = new GenerationResponse(
            text: 'Some text',
            messages: [],
            toolCalls: $toolCalls,
            model: 'test',
        );

        $result = $parser->parse($response);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(ToolCall::class, $result[0]);
        $this->assertSame('get_weather', $result[0]->name);
    }

    public function test_delegates_to_wrapped_parser_when_no_tool_calls(): void
    {
        $wrappedParser = new StringParser;
        $parser = new LlmFunctionParser($wrappedParser);

        $response = new GenerationResponse(
            text: '  Hello World  ',
            messages: [],
            toolCalls: [],
            model: 'test',
        );

        $result = $parser->parse($response);

        $this->assertSame('Hello World', $result);
    }

    public function test_has_function_call_target(): void
    {
        $parser = new LlmFunctionParser(new StringParser);

        $this->assertSame(ParserTarget::FunctionCall, $parser->getTarget());
    }

    public function test_get_wrapped_parser(): void
    {
        $wrappedParser = new StringParser;
        $parser = new LlmFunctionParser($wrappedParser);

        $this->assertSame($wrappedParser, $parser->getWrappedParser());
    }

    public function test_is_tool_call_result(): void
    {
        $toolCalls = [new ToolCall('id', 'name', [])];

        $this->assertTrue(LlmFunctionParser::isToolCallResult($toolCalls));
        $this->assertFalse(LlmFunctionParser::isToolCallResult('string'));
        $this->assertFalse(LlmFunctionParser::isToolCallResult([]));
        $this->assertFalse(LlmFunctionParser::isToolCallResult(['not', 'tool', 'calls']));
    }

    public function test_extract_function_calls(): void
    {
        $parser = new LlmFunctionParser(new StringParser);

        $toolCalls = [
            new ToolCall('call_abc', 'search', ['query' => 'test']),
        ];

        $response = new GenerationResponse(
            text: null,
            messages: [],
            toolCalls: $toolCalls,
            model: 'test',
        );

        $extracted = $parser->extractFunctionCalls($response);

        $this->assertCount(1, $extracted);
        $this->assertSame('search', $extracted[0]['name']);
        $this->assertSame(['query' => 'test'], $extracted[0]['arguments']);
        $this->assertSame('call_abc', $extracted[0]['id']);
    }

    public function test_extract_function_calls_empty(): void
    {
        $parser = new LlmFunctionParser(new StringParser);

        $response = new GenerationResponse(
            text: 'Just text',
            messages: [],
            toolCalls: [],
            model: 'test',
        );

        $extracted = $parser->extractFunctionCalls($response);

        $this->assertSame([], $extracted);
    }
}
