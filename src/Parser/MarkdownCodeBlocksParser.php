<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Parser;

use HelgeSverre\Synapse\Provider\Response\GenerationResponse;

/**
 * Extracts all code blocks from markdown as an array of objects.
 *
 * @extends BaseParser<list<array{language: string|null, code: string}>>
 */
final class MarkdownCodeBlocksParser extends BaseParser
{
    /** @return list<array{language: string|null, code: string}> */
    public function parse(GenerationResponse $response): array
    {
        $text = $this->getTextContent($response);
        $blocks = [];

        // Match all code blocks with optional language
        if (preg_match_all('/```(\w+)?\s*\n?(.*?)\n?```/s', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $blocks[] = [
                    'language' => $match[1] !== '' ? $match[1] : null,
                    'code' => trim($match[2]),
                ];
            }
        }

        return $blocks;
    }
}
