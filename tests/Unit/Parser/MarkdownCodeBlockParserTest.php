<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Parser;

use HelgeSverre\Synapse\Parser\MarkdownCodeBlockParser;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use PHPUnit\Framework\TestCase;

final class MarkdownCodeBlockParserTest extends TestCase
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

    public function test_extracts_code_block(): void
    {
        $parser = new MarkdownCodeBlockParser;
        $text = "Here is the code:\n```\necho 'hello';\n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame("echo 'hello';", $result);
    }

    public function test_extracts_code_block_with_language(): void
    {
        $parser = new MarkdownCodeBlockParser;
        $text = "```php\n<?php\necho 'hello';\n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame("<?php\necho 'hello';", $result);
    }

    public function test_extracts_specific_language(): void
    {
        $parser = new MarkdownCodeBlockParser(language: 'python');
        $text = "```php\necho 'php';\n```\n\n```python\nprint('python')\n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame("print('python')", $result);
    }

    public function test_extracts_first_block_only(): void
    {
        $parser = new MarkdownCodeBlockParser(firstOnly: true);
        $text = "```\nfirst\n```\n\n```\nsecond\n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame('first', $result);
    }

    public function test_extracts_all_blocks(): void
    {
        $parser = new MarkdownCodeBlockParser(firstOnly: false);
        $text = "```\nfirst\n```\n\n```\nsecond\n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame("first\n\nsecond", $result);
    }

    public function test_parse_all_returns_array(): void
    {
        $parser = new MarkdownCodeBlockParser;
        $text = "```\nblock1\n```\n\n```\nblock2\n```";
        $result = $parser->parseAll($this->createResponse($text));

        $this->assertSame(['block1', 'block2'], $result);
    }

    public function test_returns_empty_string_for_no_code_block(): void
    {
        $parser = new MarkdownCodeBlockParser;
        $result = $parser->parse($this->createResponse('No code here'));

        $this->assertSame('', $result);
    }

    public function test_returns_empty_array_for_no_code_blocks(): void
    {
        $parser = new MarkdownCodeBlockParser;
        $result = $parser->parseAll($this->createResponse('No code here'));

        $this->assertSame([], $result);
    }

    public function test_trims_code_block_content(): void
    {
        $parser = new MarkdownCodeBlockParser;
        $text = "```\n  padded content  \n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame('padded content', $result);
    }
}
