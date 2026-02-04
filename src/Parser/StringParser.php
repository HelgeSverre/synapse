<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Parser;

use HelgeSverre\Synapse\Provider\Response\GenerationResponse;

/**
 * @extends BaseParser<string>
 */
final class StringParser extends BaseParser
{
    public function __construct(
        private readonly bool $trim = true,
    ) {
        parent::__construct();
    }

    public function parse(GenerationResponse $response): string
    {
        $text = $this->getTextContent($response);

        return $this->trim ? trim($text) : $text;
    }
}
