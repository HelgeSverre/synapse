<?php

declare(strict_types=1);

namespace LlmExe\Parser;

use LlmExe\Provider\Response\GenerationResponse;

/**
 * @extends BaseParser<int|float>
 */
final class NumberParser extends BaseParser
{
    public function __construct(
        private readonly bool $intOnly = false,
    ) {
        parent::__construct();
    }

    public function parse(GenerationResponse $response): int|float
    {
        $text = trim($this->getTextContent($response));

        // Extract first number from the text
        if (preg_match('/-?[\d,]+\.?\d*/', $text, $matches)) {
            $number = str_replace(',', '', $matches[0]);

            if ($this->intOnly) {
                return (int) $number;
            }

            return str_contains($number, '.') ? (float) $number : (int) $number;
        }

        return 0;
    }
}
