<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\Prompt;

use HelgeSverre\Synapse\Prompt\Template\Template;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TemplateTest extends TestCase
{
    public function test_simple_variable_replacement(): void
    {
        $template = new Template('Hello {{name}}!');
        $result = $template->render(['name' => 'World']);

        $this->assertSame('Hello World!', $result);
    }

    public function test_multiple_variables(): void
    {
        $template = new Template('{{greeting}}, {{name}}! Welcome to {{place}}.');
        $result = $template->render([
            'greeting' => 'Hello',
            'name' => 'Alice',
            'place' => 'Wonderland',
        ]);

        $this->assertSame('Hello, Alice! Welcome to Wonderland.', $result);
    }

    public function test_nested_path_replacement(): void
    {
        $template = new Template('Hello {{user.name}}!');
        $result = $template->render(['user' => ['name' => 'Alice']]);

        $this->assertSame('Hello Alice!', $result);
    }

    public function test_deeply_nested_path(): void
    {
        $template = new Template('City: {{user.address.city}}');
        $result = $template->render([
            'user' => [
                'address' => [
                    'city' => 'New York',
                ],
            ],
        ]);

        $this->assertSame('City: New York', $result);
    }

    public function test_nested_path_with_object(): void
    {
        $user = new class
        {
            public string $name = 'Bob';
        };

        $template = new Template('Hello {{user.name}}!');
        $result = $template->render(['user' => $user]);

        $this->assertSame('Hello Bob!', $result);
    }

    public function test_missing_variable_non_strict(): void
    {
        $template = new Template('Hello {{name}}!', strict: false);
        $result = $template->render([]);

        $this->assertSame('Hello !', $result);
    }

    public function test_missing_variable_strict(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing template variable: name');

        $template = new Template('Hello {{name}}!', strict: true);
        $template->render([]);
    }

    public function test_missing_nested_variable_strict(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing template variable: user.name');

        $template = new Template('Hello {{user.name}}!', strict: true);
        $template->render(['user' => []]);
    }

    public function test_helper_function(): void
    {
        $template = new Template('Hello {{upper name}}!');
        $template->registerHelper('upper', fn ($s) => strtoupper((string) $s));
        $result = $template->render(['name' => 'world']);

        $this->assertSame('Hello WORLD!', $result);
    }

    public function test_multiple_helpers(): void
    {
        $template = new Template('{{upper name}} - {{lower title}}');
        $template->registerHelper('upper', fn ($s) => strtoupper((string) $s));
        $template->registerHelper('lower', fn ($s) => strtolower((string) $s));

        $result = $template->render(['name' => 'Alice', 'title' => 'QUEEN']);

        $this->assertSame('ALICE - queen', $result);
    }

    public function test_helper_with_nested_path(): void
    {
        $template = new Template('{{upper user.name}}');
        $template->registerHelper('upper', fn ($s) => strtoupper((string) $s));

        $result = $template->render(['user' => ['name' => 'alice']]);

        $this->assertSame('ALICE', $result);
    }

    public function test_partial_inclusion(): void
    {
        $template = new Template('{{> greeting}}');
        $template->registerPartial('greeting', 'Hello World!');
        $result = $template->render([]);

        $this->assertSame('Hello World!', $result);
    }

    public function test_partial_with_spaces(): void
    {
        $template = new Template('{{>   spacedPartial   }}');
        $template->registerPartial('spacedPartial', 'Content');
        $result = $template->render([]);

        $this->assertSame('Content', $result);
    }

    public function test_missing_partial_non_strict(): void
    {
        $template = new Template('Start {{> missing}} End', strict: false);
        $result = $template->render([]);

        $this->assertSame('Start  End', $result);
    }

    public function test_missing_partial_strict(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown partial: missing');

        $template = new Template('{{> missing}}', strict: true);
        $template->render([]);
    }

    public function test_array_to_json(): void
    {
        $template = new Template('Data: {{data}}');
        $result = $template->render(['data' => ['a' => 1, 'b' => 2]]);

        $this->assertSame('Data: {"a":1,"b":2}', $result);
    }

    public function test_nested_array_to_json(): void
    {
        $template = new Template('{{config}}');
        $result = $template->render([
            'config' => [
                'database' => [
                    'host' => 'localhost',
                    'port' => 3306,
                ],
            ],
        ]);

        $this->assertSame('{"database":{"host":"localhost","port":3306}}', $result);
    }

    #[DataProvider('booleanValuesProvider')]
    public function test_boolean_values(bool $value, string $expected): void
    {
        $template = new Template('Value: {{flag}}');
        $result = $template->render(['flag' => $value]);

        $this->assertSame("Value: {$expected}", $result);
    }

    public static function booleanValuesProvider(): array
    {
        return [
            [true, 'true'],
            [false, 'false'],
        ];
    }

    #[DataProvider('numericValuesProvider')]
    public function test_numeric_values(int|float $value, string $expected): void
    {
        $template = new Template('Number: {{num}}');
        $result = $template->render(['num' => $value]);

        $this->assertSame("Number: {$expected}", $result);
    }

    public static function numericValuesProvider(): array
    {
        return [
            [42, '42'],
            [3.14, '3.14'],
            [0, '0'],
            [-100, '-100'],
            [1.5e10, '15000000000'],
        ];
    }

    public function test_stringable_object(): void
    {
        $obj = new class implements \Stringable
        {
            public function __toString(): string
            {
                return 'Stringable Object';
            }
        };

        $template = new Template('Result: {{obj}}');
        $result = $template->render(['obj' => $obj]);

        $this->assertSame('Result: Stringable Object', $result);
    }

    public function test_null_value(): void
    {
        $template = new Template('Value: {{val}}', strict: false);
        $result = $template->render(['val' => null]);

        $this->assertSame('Value: ', $result);
    }

    public function test_empty_string(): void
    {
        $template = new Template('Value: {{val}}');
        $result = $template->render(['val' => '']);

        $this->assertSame('Value: ', $result);
    }

    public function test_whitespace_in_variable_name(): void
    {
        $template = new Template('Hello {{ name }}!');
        // Variables must not have spaces in the name, so this won't match
        $result = $template->render(['name' => 'World']);

        // Should not replace because of spaces
        $this->assertSame('Hello {{ name }}!', $result);
    }

    public function test_multiple_same_variable(): void
    {
        $template = new Template('{{name}} meets {{name}}');
        $result = $template->render(['name' => 'Alice']);

        $this->assertSame('Alice meets Alice', $result);
    }

    public function test_special_characters_in_value(): void
    {
        $template = new Template('Code: {{code}}');
        $result = $template->render(['code' => '<script>alert("xss")</script>']);

        $this->assertSame('Code: <script>alert("xss")</script>', $result);
    }

    public function test_unicode_in_value(): void
    {
        $template = new Template('Greeting: {{msg}}');
        $result = $template->render(['msg' => '你好世界 🌍']);

        $this->assertSame('Greeting: 你好世界 🌍', $result);
    }

    public function test_newlines_in_value(): void
    {
        $template = new Template('Text: {{text}}');
        $result = $template->render(['text' => "Line1\nLine2\nLine3"]);

        $this->assertSame("Text: Line1\nLine2\nLine3", $result);
    }

    public function test_complex_template(): void
    {
        $template = new Template(
            "System: {{system}}\n\nUser: {{user.name}} says: {{message}}\n\n{{> footer}}",
        );
        $template->registerPartial('footer', "---\nPowered by LLM-Exe");

        $result = $template->render([
            'system' => 'You are a helpful assistant.',
            'user' => ['name' => 'Alice'],
            'message' => 'Hello!',
        ]);

        $expected = "System: You are a helpful assistant.\n\nUser: Alice says: Hello!\n\n---\nPowered by LLM-Exe";
        $this->assertSame($expected, $result);
    }

    public function test_helper_returning_non_string(): void
    {
        $template = new Template('{{double num}}');
        $template->registerHelper('double', fn ($n): string => (string) ($n * 2));

        $result = $template->render(['num' => 21]);

        $this->assertSame('42', $result);
    }

    public function test_chained_helper_calls(): void
    {
        $template = new Template('{{wrap text}} and {{wrap other}}');
        $template->registerHelper('wrap', fn ($s): string => "[{$s}]");

        $result = $template->render(['text' => 'first', 'other' => 'second']);

        $this->assertSame('[first] and [second]', $result);
    }

    public function test_empty_template(): void
    {
        $template = new Template('');
        $result = $template->render(['anything' => 'value']);

        $this->assertSame('', $result);
    }

    public function test_template_with_no_variables(): void
    {
        $template = new Template('Just plain text.');
        $result = $template->render([]);

        $this->assertSame('Just plain text.', $result);
    }

    public function test_indexed_array_access(): void
    {
        $template = new Template('First: {{items.0}}, Second: {{items.1}}');
        $result = $template->render(['items' => ['apple', 'banana']]);

        $this->assertSame('First: apple, Second: banana', $result);
    }

    public function test_fluent_helper_registration(): void
    {
        $template = new Template('{{upper name}}');
        $fluent = $template->registerHelper('upper', fn ($s) => strtoupper((string) $s));

        $this->assertSame($template, $fluent);
    }

    public function test_fluent_partial_registration(): void
    {
        $template = new Template('{{> test}}');
        $fluent = $template->registerPartial('test', 'content');

        $this->assertSame($template, $fluent);
    }
}
