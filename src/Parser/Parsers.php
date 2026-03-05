<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Parser;

use HelgeSverre\Synapse\Parser\JsonSchema\JsonSchemaValidatorInterface;

final class Parsers
{
    public static function string(bool $trim = true): StringParser
    {
        return new StringParser($trim);
    }

    public static function boolean(): BooleanParser
    {
        return new BooleanParser;
    }

    public static function number(bool $intOnly = false): NumberParser
    {
        return new NumberParser($intOnly);
    }

    public static function json(
        ?array $schema = null,
        bool $validateSchema = false,
        ?JsonSchemaValidatorInterface $validator = null,
    ): JsonParser {
        return new JsonParser($schema, $validateSchema, $validator);
    }

    public static function list(): ListParser
    {
        return new ListParser;
    }

    public static function codeBlock(?string $language = null, bool $firstOnly = true): MarkdownCodeBlockParser
    {
        return new MarkdownCodeBlockParser($language, $firstOnly);
    }

    public static function codeBlocks(): MarkdownCodeBlocksParser
    {
        return new MarkdownCodeBlocksParser;
    }

    /**
     * @param  list<string>  $values
     */
    public static function enum(array $values, bool $caseSensitive = false): EnumParser
    {
        return new EnumParser($values, $caseSensitive);
    }

    public static function keyValue(string $separator = ':', bool $trimValues = true): ListToKeyValueParser
    {
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be a non-empty string');
        }

        /** @var non-empty-string $separator */
        return new ListToKeyValueParser($separator, $trimValues);
    }

    public static function listJson(string $separator = ':', int $indentSpaces = 2): ListToJsonParser
    {
        if ($separator === '') {
            throw new \InvalidArgumentException('separator must be a non-empty string');
        }

        /** @var non-empty-string $separator */
        return new ListToJsonParser($separator, $indentSpaces);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    public static function template(array $replacements = [], bool $strict = false): ReplaceStringTemplateParser
    {
        return (new ReplaceStringTemplateParser($strict))->withReplacements($replacements);
    }

    public static function tool(?ParserInterface $wrappedParser = null): LlmFunctionParser
    {
        return new LlmFunctionParser($wrappedParser ?? new StringParser);
    }

    /**
     * @param  callable(mixed): mixed  $handler
     */
    public static function custom(callable $handler): CustomParser
    {
        return new CustomParser($handler);
    }
}
