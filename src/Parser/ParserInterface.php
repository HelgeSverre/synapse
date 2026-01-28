<?php

declare(strict_types=1);

namespace LlmExe\Parser;

use LlmExe\Provider\Response\GenerationResponse;

/**
 * @template T
 */
interface ParserInterface
{
    /**
     * @return T
     */
    public function parse(GenerationResponse $response): mixed;

    public function getTarget(): ParserTarget;
}
