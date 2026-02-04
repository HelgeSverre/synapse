<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Parser;

use HelgeSverre\Synapse\Provider\Response\GenerationResponse;

/**
 * Parses numbered or bulleted lists into an array.
 *
 * @extends BaseParser<list<string>>
 */
final class ListParser extends BaseParser
{
    /** @return list<string> */
    public function parse(GenerationResponse $response): array
    {
        $text = trim($this->getTextContent($response));
        $lines = preg_split('/\r?\n/', $text) ?: [];
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Remove list markers: "1.", "1)", "-", "*", "•"
            $line = preg_replace('/^(?:\d+[.)]\s*|[-*•]\s*)/u', '', $line);
            if ($line !== null) {
                $line = trim($line);
                if ($line !== '') {
                    $items[] = $line;
                }
            }
        }

        return $items;
    }
}
