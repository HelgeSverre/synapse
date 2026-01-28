<?php

declare(strict_types=1);

namespace LlmExe\Executor;

use LlmExe\Hooks\HookDispatcherInterface;
use LlmExe\Provider\Response\GenerationResponse;
use LlmExe\Provider\Response\UsageInfo;
use LlmExe\State\ConversationState;

/**
 * Wraps a simple function as an executor.
 *
 * @template I of array<string, mixed>
 * @template O
 *
 * @extends BaseExecutor<I, O>
 */
final class CoreExecutor extends BaseExecutor
{
    /** @var callable(I): O */
    private $handlerFn;

    /**
     * @param  callable(I): O  $handler
     */
    public function __construct(
        callable $handler,
        ?string $name = null,
        ?HookDispatcherInterface $hooks = null,
        ?ConversationState $state = null,
    ) {
        parent::__construct($name ?? 'CoreExecutor', $hooks, $state);
        $this->handlerFn = $handler;
    }

    protected function handler(mixed $input): ExecutionResult
    {
        $result = ($this->handlerFn)($input);

        // Create a minimal response for core executors
        $response = new GenerationResponse(
            text: is_string($result) ? $result : (json_encode($result) ?: ''),
            messages: [],
            toolCalls: [],
            model: 'core',
            usage: new UsageInfo(0, 0),
        );

        return new ExecutionResult(
            value: $result,
            state: $this->state,
            response: $response,
        );
    }
}
