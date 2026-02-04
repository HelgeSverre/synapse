<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Prompt;

use HelgeSverre\Synapse\Prompt\PromptType;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use PHPUnit\Framework\TestCase;

final class TextPromptTest extends TestCase
{
    public function test_add_content(): void
    {
        $prompt = new TextPrompt;
        $prompt->addContent('First paragraph.');
        $prompt->addContent('Second paragraph.');

        $result = $prompt->render([]);

        $this->assertSame("First paragraph.\n\nSecond paragraph.", $result);
    }

    public function test_set_content(): void
    {
        $prompt = new TextPrompt;
        $prompt->addContent('Will be replaced.');
        $prompt->setContent('New content.');

        $result = $prompt->render([]);

        $this->assertSame('New content.', $result);
    }

    public function test_variable_replacement(): void
    {
        $prompt = new TextPrompt;
        $prompt->setContent('Hello {{name}}! You are {{age}} years old.');

        $result = $prompt->render(['name' => 'Alice', 'age' => 30]);

        $this->assertSame('Hello Alice! You are 30 years old.', $result);
    }

    public function test_nested_variables(): void
    {
        $prompt = new TextPrompt;
        $prompt->setContent('User: {{user.name}}, Email: {{user.email}}');

        $result = $prompt->render([
            'user' => [
                'name' => 'Bob',
                'email' => 'bob@example.com',
            ],
        ]);

        $this->assertSame('User: Bob, Email: bob@example.com', $result);
    }

    public function test_helper(): void
    {
        $prompt = new TextPrompt;
        $prompt->registerHelper('upper', fn ($s) => strtoupper((string) $s));
        $prompt->setContent('{{upper greeting}}');

        $result = $prompt->render(['greeting' => 'hello']);

        $this->assertSame('HELLO', $result);
    }

    public function test_partial(): void
    {
        $prompt = new TextPrompt;
        $prompt->registerPartial('header', 'Welcome to the system!');
        $prompt->setContent("{{> header}}\n\nPlease proceed.");

        $result = $prompt->render([]);

        $this->assertSame("Welcome to the system!\n\nPlease proceed.", $result);
    }

    public function test_strict_mode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $prompt = new TextPrompt;
        $prompt->strict(true);
        $prompt->setContent('Missing {{variable}}');
        $prompt->render([]);
    }

    public function test_non_strict_mode(): void
    {
        $prompt = new TextPrompt;
        $prompt->strict(false);
        $prompt->setContent('Missing {{variable}} here.');

        $result = $prompt->render([]);

        $this->assertSame('Missing  here.', $result);
    }

    public function test_get_type(): void
    {
        $prompt = new TextPrompt;
        $this->assertSame(PromptType::Text, $prompt->getType());
    }

    public function test_fluent_interface(): void
    {
        $prompt = (new TextPrompt)
            ->addContent('Line 1')
            ->addContent('Line 2')
            ->registerHelper('test', fn ($s): mixed => $s)
            ->registerPartial('part', 'content')
            ->strict(false);

        $this->assertInstanceOf(TextPrompt::class, $prompt);
    }

    public function test_empty_content(): void
    {
        $prompt = new TextPrompt;
        $result = $prompt->render([]);

        $this->assertSame('', $result);
    }

    public function test_complex_template(): void
    {
        $prompt = new TextPrompt;
        $prompt->registerHelper('format', fn ($n): string => number_format((float) $n, 2));
        $prompt->registerPartial('disclaimer', 'This is not financial advice.');

        $prompt->setContent(
            "Stock Report for {{company}}\n".
            'Current Price: ${{format price}}'."\n".
            "Change: {{change}}%\n\n".
            '{{> disclaimer}}',
        );

        $result = $prompt->render([
            'company' => 'ACME Corp',
            'price' => 150.5,
            'change' => '+2.5',
        ]);

        $expected = "Stock Report for ACME Corp\n".
            "Current Price: $150.50\n".
            "Change: +2.5%\n\n".
            'This is not financial advice.';

        $this->assertSame($expected, $result);
    }
}
