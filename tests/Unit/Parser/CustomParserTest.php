<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Parser;

use HelgeSverre\Synapse\Parser\CustomParser;
use HelgeSverre\Synapse\Parser\ParserTarget;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use PHPUnit\Framework\TestCase;

final class CustomParserTest extends TestCase
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

    public function test_calls_custom_handler(): void
    {
        $parser = new CustomParser(fn (GenerationResponse $response): string => 'Custom: '.$response->getText());

        $result = $parser->parse($this->createResponse('test'));

        $this->assertSame('Custom: test', $result);
    }

    public function test_returns_custom_type(): void
    {
        $parser = new CustomParser(fn (GenerationResponse $response): array => ['text' => $response->getText(), 'length' => strlen($response->getText() ?? '')]);

        $result = $parser->parse($this->createResponse('hello'));

        $this->assertSame(['text' => 'hello', 'length' => 5], $result);
    }

    public function test_defaults_to_text_target(): void
    {
        $parser = new CustomParser(fn ($r): ?string => $r->getText());

        $this->assertSame(ParserTarget::Text, $parser->getTarget());
    }

    public function test_allows_custom_target(): void
    {
        $parser = new CustomParser(fn ($r): array => $r->getToolCalls(), ParserTarget::FunctionCall);

        $this->assertSame(ParserTarget::FunctionCall, $parser->getTarget());
    }

    public function test_accesses_full_response(): void
    {
        $parser = new CustomParser(fn (GenerationResponse $response): array => [
            'text' => $response->getText(),
            'model' => $response->model,
            'hasTools' => $response->hasToolCalls(),
        ]);

        $result = $parser->parse($this->createResponse('test'));

        $this->assertSame([
            'text' => 'test',
            'model' => 'test',
            'hasTools' => false,
        ], $result);
    }

    public function test_can_throw_exceptions(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Parse failed');

        $parser = new CustomParser(function (GenerationResponse $response): never {
            throw new \RuntimeException('Parse failed');
        });

        $parser->parse($this->createResponse('test'));
    }
}
