<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Examples\Profilinator2000;

use HelgeSverre\Synapse\Executor\ToolExecutorInterface;
use HelgeSverre\Synapse\Executor\ToolResult;
use HelgeSverre\Synapse\Provider\Request\ToolDefinition;
use HelgeSverre\Synapse\State\ConversationState;

/**
 * Decorator that bounds large tool payloads before they are fed back into the model.
 */
final class SafeToolExecutor implements ToolExecutorInterface
{
    public function __construct(
        private readonly ToolExecutorInterface $inner,
        private readonly int $maxChars = 8000,
    ) {}

    /** @return list<ToolDefinition> */
    public function getToolDefinitions(): array
    {
        return $this->inner->getToolDefinitions();
    }

    public function callFunctionResult(string $name, array $input, ?ConversationState $state = null): ToolResult
    {
        $result = $this->inner->callFunctionResult($name, $input, $state);

        if (! $result->success) {
            return $result;
        }

        if (is_string($result->result)) {
            $truncated = $this->truncate($name, $result->result);
            if ($truncated === $result->result) {
                return $result;
            }

            return ToolResult::success($truncated, $result->attributes);
        }

        $json = json_encode($result->result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || strlen($json) <= $this->maxChars) {
            return $result;
        }

        return ToolResult::success([
            'truncated' => true,
            'tool' => $name,
            'total_chars' => strlen($json),
            'preview' => substr($json, 0, $this->maxChars),
        ], $result->attributes);
    }

    private function truncate(string $tool, string $payload): string
    {
        if (strlen($payload) <= $this->maxChars) {
            return $payload;
        }

        $preview = substr($payload, 0, $this->maxChars);

        return "[truncated tool output from {$tool}: ".strlen($payload)." chars]\n{$preview}";
    }
}
