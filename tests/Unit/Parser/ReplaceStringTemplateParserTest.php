<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Parser;

use LlmExe\Parser\ReplaceStringTemplateParser;
use LlmExe\Provider\Response\GenerationResponse;
use PHPUnit\Framework\TestCase;

final class ReplaceStringTemplateParserTest extends TestCase
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

    public function test_replaces_variables(): void
    {
        $parser = new ReplaceStringTemplateParser;
        $parser->setReplacement('name', 'Alice');
        $parser->setReplacement('greeting', 'Hello');

        $result = $parser->parse($this->createResponse('{{greeting}}, {{name}}!'));

        $this->assertSame('Hello, Alice!', $result);
    }

    public function test_with_replacements_returns_new_instance(): void
    {
        $parser1 = new ReplaceStringTemplateParser;
        $parser2 = $parser1->withReplacements(['name' => 'Bob']);

        $this->assertNotSame($parser1, $parser2);
        $this->assertSame([], $parser1->getReplacements());
        $this->assertSame(['name' => 'Bob'], $parser2->getReplacements());
    }

    public function test_merges_replacements(): void
    {
        $parser = (new ReplaceStringTemplateParser)
            ->withReplacements(['a' => '1'])
            ->withReplacements(['b' => '2']);

        $this->assertSame(['a' => '1', 'b' => '2'], $parser->getReplacements());
    }

    public function test_custom_helper(): void
    {
        $parser = new ReplaceStringTemplateParser;
        $parser->setReplacement('name', 'alice');
        $parser->registerHelper('upper', fn ($s) => strtoupper((string) $s));

        $result = $parser->parse($this->createResponse('{{upper name}}'));

        $this->assertSame('ALICE', $result);
    }

    public function test_non_strict_mode(): void
    {
        $parser = new ReplaceStringTemplateParser(strict: false);

        $result = $parser->parse($this->createResponse('Hello {{missing}}!'));

        $this->assertSame('Hello !', $result);
    }

    public function test_strict_mode_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $parser = new ReplaceStringTemplateParser(strict: true);
        $parser->parse($this->createResponse('Hello {{missing}}!'));
    }

    public function test_trims_output(): void
    {
        $parser = new ReplaceStringTemplateParser;
        $parser->setReplacement('value', 'test');

        $result = $parser->parse($this->createResponse('  {{value}}  '));

        $this->assertSame('test', $result);
    }

    public function test_nested_paths(): void
    {
        $parser = new ReplaceStringTemplateParser;
        $parser->setReplacement('user', ['name' => 'Charlie']);

        $result = $parser->parse($this->createResponse('Hi {{user.name}}'));

        $this->assertSame('Hi Charlie', $result);
    }
}
