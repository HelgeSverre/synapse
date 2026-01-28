<?php

declare(strict_types=1);

namespace LlmExe\Parser;

use LlmExe\Provider\Response\GenerationResponse;

/**
 * Parses key: value lists into an associative array.
 *
 * @extends BaseParser<array<string, string>>
 */
final class ListToKeyValueParser extends BaseParser
{
    /**
     * @param  non-empty-string  $separator
     */
    public function __construct(
        private readonly string $separator = ':',
        private readonly bool $trimValues = true,
    ) {
        parent::__construct();
    }

    /** @return array<string, string> */
    public function parse(GenerationResponse $response): array
    {
        $text = $this->getTextContent($response);
        $lines = preg_split('/\r?\n/', $text) ?: [];
        $result = [];

        foreach ($lines as $line) {
            // Check if line is empty (after trim for this check only)
            if (trim($line) === '') {
                continue;
            }

            // Remove leading whitespace and list markers: "1.", "1)", "-", "*", "•"
            $processedLine = preg_replace('/^\s*(?:\d+[.)]\s*|[-*•]\s*)?/', '', $line) ?? $line;

            // Split by separator
            $parts = explode($this->separator, $processedLine, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = $this->trimValues ? trim($parts[1]) : $parts[1];
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
