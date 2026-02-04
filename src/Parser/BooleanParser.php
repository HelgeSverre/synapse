<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Parser;

use HelgeSverre\Synapse\Provider\Response\GenerationResponse;

/**
 * @extends BaseParser<bool>
 */
final class BooleanParser extends BaseParser
{
    /** @var list<string> */
    private array $trueValues = ['yes', 'true', '1', 'correct', 'affirmative'];

    /** @var list<string> */
    private array $falseValues = ['no', 'false', '0', 'incorrect', 'negative'];

    public function parse(GenerationResponse $response): bool
    {
        $text = strtolower(trim($this->getTextContent($response)));

        if (in_array($text, $this->trueValues, true)) {
            return true;
        }

        if (in_array($text, $this->falseValues, true)) {
            return false;
        }

        // Check if text contains true/false indicators
        foreach ($this->trueValues as $value) {
            if (str_contains($text, $value)) {
                return true;
            }
        }

        foreach ($this->falseValues as $value) {
            if (str_contains($text, $value)) {
                return false;
            }
        }

        return false;
    }
}
