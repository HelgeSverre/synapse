<?php

declare(strict_types=1);

namespace LlmExe\Parser;

use LlmExe\Provider\Response\GenerationResponse;

/**
 * Extracts code from markdown code blocks.
 *
 * @extends BaseParser<string>
 */
final class MarkdownCodeBlockParser extends BaseParser
{
    public function __construct(
        private readonly ?string $language = null,
        private readonly bool $firstOnly = true,
    ) {
        parent::__construct();
    }

    public function parse(GenerationResponse $response): string
    {
        $text = $this->getTextContent($response);

        $pattern = $this->language !== null
            ? '/```'.preg_quote($this->language, '/').'\s*\n?(.*?)\n?```/s'
            : '/```(?:\w+)?\s*\n?(.*?)\n?```/s';

        if (preg_match_all($pattern, $text, $matches)) {
            if ($this->firstOnly) {
                return trim($matches[1][0] ?? '');
            }

            return implode("\n\n", array_map(trim(...), $matches[1]));
        }

        return '';
    }

    /** @return list<string> */
    public function parseAll(GenerationResponse $response): array
    {
        $text = $this->getTextContent($response);

        $pattern = $this->language !== null
            ? '/```'.preg_quote($this->language, '/').'\s*\n?(.*?)\n?```/s'
            : '/```(?:\w+)?\s*\n?(.*?)\n?```/s';

        if (preg_match_all($pattern, $text, $matches)) {
            return array_map(trim(...), $matches[1]);
        }

        return [];
    }
}
