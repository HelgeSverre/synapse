<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Parser;

use LlmExe\Parser\MarkdownCodeBlocksParser;
use LlmExe\Provider\Response\GenerationResponse;
use PHPUnit\Framework\TestCase;

final class MarkdownCodeBlocksParserTest extends TestCase
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

    public function test_extracts_single_block(): void
    {
        $parser = new MarkdownCodeBlocksParser;
        $text = "```php\necho 'hello';\n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertCount(1, $result);
        $this->assertSame('php', $result[0]['language']);
        $this->assertSame("echo 'hello';", $result[0]['code']);
    }

    public function test_extracts_multiple_blocks(): void
    {
        $parser = new MarkdownCodeBlocksParser;
        $text = "```php\necho 'php';\n```\n\nSome text\n\n```python\nprint('python')\n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertCount(2, $result);
        $this->assertSame('php', $result[0]['language']);
        $this->assertSame("echo 'php';", $result[0]['code']);
        $this->assertSame('python', $result[1]['language']);
        $this->assertSame("print('python')", $result[1]['code']);
    }

    public function test_handles_block_without_language(): void
    {
        $parser = new MarkdownCodeBlocksParser;
        $text = "```\nplain code\n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['language']);
        $this->assertSame('plain code', $result[0]['code']);
    }

    public function test_returns_empty_array_for_no_blocks(): void
    {
        $parser = new MarkdownCodeBlocksParser;
        $result = $parser->parse($this->createResponse('No code blocks here'));

        $this->assertSame([], $result);
    }

    public function test_extracts_mixed_languages(): void
    {
        $parser = new MarkdownCodeBlocksParser;
        $text = "```js\nconsole.log('js');\n```\n\n```\nno lang\n```\n\n```sql\nSELECT *\n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertCount(3, $result);
        $this->assertSame('js', $result[0]['language']);
        $this->assertNull($result[1]['language']);
        $this->assertSame('sql', $result[2]['language']);
    }

    public function test_trims_code_content(): void
    {
        $parser = new MarkdownCodeBlocksParser;
        $text = "```\n  padded  \n```";
        $result = $parser->parse($this->createResponse($text));

        $this->assertSame('padded', $result[0]['code']);
    }
}
