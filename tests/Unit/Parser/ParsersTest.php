<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Parser;

use HelgeSverre\Synapse\Parser\EnumParser;
use HelgeSverre\Synapse\Parser\JsonParser;
use HelgeSverre\Synapse\Parser\MarkdownCodeBlockParser;
use HelgeSverre\Synapse\Parser\Parsers;
use HelgeSverre\Synapse\Parser\StringParser;
use PHPUnit\Framework\TestCase;

final class ParsersTest extends TestCase
{
    public function test_string_factory_returns_string_parser(): void
    {
        $parser = Parsers::string();

        $this->assertInstanceOf(StringParser::class, $parser);
    }

    public function test_json_factory_returns_json_parser(): void
    {
        $parser = Parsers::json(validateSchema: true);

        $this->assertInstanceOf(JsonParser::class, $parser);
    }

    public function test_code_block_factory_returns_markdown_code_block_parser(): void
    {
        $parser = Parsers::codeBlock('php');

        $this->assertInstanceOf(MarkdownCodeBlockParser::class, $parser);
    }

    public function test_enum_factory_returns_enum_parser(): void
    {
        $parser = Parsers::enum(['a', 'b']);

        $this->assertInstanceOf(EnumParser::class, $parser);
    }
}
