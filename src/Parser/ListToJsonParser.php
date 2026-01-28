<?php

declare(strict_types=1);

namespace LlmExe\Parser;

use LlmExe\Provider\Response\GenerationResponse;

/**
 * Parses structured lists into JSON objects.
 * Supports nested structures using indentation.
 *
 * @extends BaseParser<array<string, mixed>>
 */
final class ListToJsonParser extends BaseParser
{
    /**
     * @param  non-empty-string  $separator
     */
    public function __construct(
        private readonly string $separator = ':',
        private readonly int $indentSpaces = 2,
    ) {
        parent::__construct();
    }

    /** @return array<string, mixed> */
    public function parse(GenerationResponse $response): array
    {
        $text = trim($this->getTextContent($response));
        $lines = preg_split('/\r?\n/', $text) ?: [];

        return $this->parseLines($lines, 0);
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, mixed>
     */
    private function parseLines(array $lines, int $baseIndent): array
    {
        $result = [];
        $i = 0;

        while ($i < count($lines)) {
            $line = $lines[$i];
            $indent = $this->getIndentLevel($line);
            $line = trim($line);

            if ($line === '' || $indent < $baseIndent) {
                $i++;

                continue;
            }

            // Remove list markers
            $line = preg_replace('/^(?:\d+[.)]\s*|[-*•]\s*)/', '', $line) ?? $line;

            // Check if this is a key-value pair
            $parts = explode($this->separator, $line, 2);
            $key = trim($parts[0]);

            if (count($parts) === 2 && trim($parts[1]) !== '') {
                // Simple key: value
                $result[$key] = $this->parseValue(trim($parts[1]));
                $i++;
            } else {
                // Check for nested content
                $childLines = [];
                $j = $i + 1;

                while ($j < count($lines)) {
                    $childIndent = $this->getIndentLevel($lines[$j]);
                    if ($childIndent <= $indent && trim($lines[$j]) !== '') {
                        break;
                    }
                    $childLines[] = $lines[$j];
                    $j++;
                }

                $result[$key] = count($childLines) > 0 ? $this->parseLines($childLines, $indent + $this->indentSpaces) : null;

                $i = $j;
            }
        }

        return $result;
    }

    private function getIndentLevel(string $line): int
    {
        $trimmed = ltrim($line);

        return strlen($line) - strlen($trimmed);
    }

    private function parseValue(string $value): mixed
    {
        // Try to parse as JSON primitive
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }
        if ($value === 'null') {
            return null;
        }
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        // Check for array notation [a, b, c]
        if (preg_match('/^\[(.+)\]$/', $value, $matches)) {
            return array_map(trim(...), explode(',', $matches[1]));
        }

        return $value;
    }
}
