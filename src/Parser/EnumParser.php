<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Parser;

use HelgeSverre\Synapse\Provider\Response\GenerationResponse;

/**
 * Extracts a value from a list of allowed values.
 *
 * @extends BaseParser<string|null>
 */
final class EnumParser extends BaseParser
{
    /** @param list<string> $allowedValues */
    public function __construct(
        private readonly array $allowedValues,
        private readonly bool $caseSensitive = false,
    ) {
        parent::__construct();
    }

    public function parse(GenerationResponse $response): ?string
    {
        $text = trim($this->getTextContent($response));

        // Direct match
        foreach ($this->allowedValues as $value) {
            if ($this->caseSensitive) {
                if ($text === $value) {
                    return $value;
                }
            } elseif (strcasecmp($text, $value) === 0) {
                return $value;
            }
        }

        // Check if text contains any allowed value
        foreach ($this->allowedValues as $value) {
            if ($this->caseSensitive) {
                if (str_contains($text, $value)) {
                    return $value;
                }
            } elseif (stripos($text, $value) !== false) {
                return $value;
            }
        }

        return null;
    }
}
