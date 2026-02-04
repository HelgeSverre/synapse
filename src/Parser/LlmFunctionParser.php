<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Parser;

use HelgeSverre\Synapse\Provider\Request\ToolCall;
use HelgeSverre\Synapse\Provider\Response\GenerationResponse;

/**
 * Wraps another parser to handle function/tool call responses.
 * When the response contains tool calls, returns them.
 * Otherwise, delegates to the wrapped parser.
 *
 * @template T
 *
 * @extends BaseParser<T|list<ToolCall>>
 */
final class LlmFunctionParser extends BaseParser
{
    /**
     * @param  ParserInterface<T>  $wrappedParser
     */
    public function __construct(
        private readonly ParserInterface $wrappedParser,
    ) {
        parent::__construct(ParserTarget::FunctionCall);
    }

    /**
     * @return T|list<ToolCall>
     */
    public function parse(GenerationResponse $response): mixed
    {
        // If response has tool calls, return them
        if ($response->hasToolCalls()) {
            return $response->getToolCalls();
        }

        // Otherwise, delegate to wrapped parser
        return $this->wrappedParser->parse($response);
    }

    /**
     * @return ParserInterface<T>
     */
    public function getWrappedParser(): ParserInterface
    {
        return $this->wrappedParser;
    }

    /**
     * Check if the parsed result is a list of tool calls.
     */
    public static function isToolCallResult(mixed $result): bool
    {
        if (! is_array($result) || $result === []) {
            return false;
        }

        return $result[0] instanceof ToolCall;
    }

    /**
     * Extract function call details from response.
     *
     * @return list<array{name: string, arguments: array<string, mixed>, id: string}>
     */
    public function extractFunctionCalls(GenerationResponse $response): array
    {
        $calls = [];

        foreach ($response->getToolCalls() as $toolCall) {
            $calls[] = [
                'name' => $toolCall->name,
                'arguments' => $toolCall->arguments,
                'id' => $toolCall->id,
            ];
        }

        return $calls;
    }
}
