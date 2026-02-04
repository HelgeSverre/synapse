<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Parser;

use HelgeSverre\Synapse\Provider\Response\GenerationResponse;

/**
 * @template T
 *
 * @implements ParserInterface<T>
 */
abstract class BaseParser implements ParserInterface
{
    public function __construct(
        protected readonly ParserTarget $target = ParserTarget::Text,
    ) {}

    public function getTarget(): ParserTarget
    {
        return $this->target;
    }

    protected function getTextContent(GenerationResponse $response): string
    {
        return $response->getText() ?? '';
    }
}
