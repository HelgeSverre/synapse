<?php

declare(strict_types=1);

namespace LlmExe\Parser;

use LlmExe\Provider\Response\GenerationResponse;

/**
 * @template T
 *
 * @extends BaseParser<T>
 */
final class CustomParser extends BaseParser
{
    /** @var callable(GenerationResponse): T */
    private $handler;

    /** @param callable(GenerationResponse): T $handler */
    public function __construct(
        callable $handler,
        ParserTarget $target = ParserTarget::Text,
    ) {
        parent::__construct($target);
        $this->handler = $handler;
    }

    /** @return T */
    public function parse(GenerationResponse $response): mixed
    {
        return ($this->handler)($response);
    }
}
