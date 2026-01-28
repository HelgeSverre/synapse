<?php

declare(strict_types=1);

namespace LlmExe\Executor;

use LlmExe\Hooks\Events\OnComplete;
use LlmExe\Hooks\Events\OnError;
use LlmExe\Hooks\Events\OnSuccess;
use LlmExe\Hooks\HookDispatcher;
use LlmExe\Hooks\HookDispatcherInterface;
use LlmExe\State\ConversationState;

/**
 * @template I of array<string, mixed>
 * @template O
 */
abstract class BaseExecutor
{
    protected ExecutorMetadata $metadata;

    protected HookDispatcherInterface $hooks;

    protected ConversationState $state;

    public function __construct(
        ?string $name = null,
        ?HookDispatcherInterface $hooks = null,
        ?ConversationState $state = null,
    ) {
        $this->metadata = ExecutorMetadata::create(static::class, $name);
        $this->hooks = $hooks ?? new HookDispatcher;
        $this->state = $state ?? new ConversationState;
    }

    /**
     * @param  I  $input
     * @return ExecutionResult<O>
     */
    public function execute(array $input): ExecutionResult
    {
        $start = hrtime(true);
        $this->metadata = $this->metadata->withExecution();

        try {
            $handlerInput = $this->getHandlerInput($input);
            $result = $this->handler($handlerInput);
            $output = $this->getHandlerOutput($result);

            $durationMs = (hrtime(true) - $start) / 1_000_000;
            $this->hooks->dispatch(new OnSuccess($output, $durationMs));
            $this->hooks->dispatch(new OnComplete(true, $durationMs));

            return $output;
        } catch (\Throwable $e) {
            $durationMs = (hrtime(true) - $start) / 1_000_000;
            $this->hooks->dispatch(new OnError($e));
            $this->hooks->dispatch(new OnComplete(false, $durationMs, $e));
            throw $e;
        }
    }

    /**
     * Transform input before handler.
     *
     * @param  I  $input
     */
    protected function getHandlerInput(array $input): mixed
    {
        return $input;
    }

    /**
     * Main handler logic (implemented by subclasses).
     *
     * @return ExecutionResult<O>
     */
    abstract protected function handler(mixed $input): ExecutionResult;

    /**
     * Transform output after handler.
     *
     * @param  ExecutionResult<O>  $output
     * @return ExecutionResult<O>
     */
    protected function getHandlerOutput(ExecutionResult $output): ExecutionResult
    {
        return $output;
    }

    public function getMetadata(): ExecutorMetadata
    {
        return $this->metadata;
    }

    public function getState(): ConversationState
    {
        return $this->state;
    }

    public function withState(ConversationState $state): static
    {
        $clone = clone $this;
        $clone->state = $state;

        return $clone;
    }

    public function withHooks(HookDispatcherInterface $hooks): static
    {
        $clone = clone $this;
        $clone->hooks = $hooks;

        return $clone;
    }

    public function getHooks(): HookDispatcherInterface
    {
        return $this->hooks;
    }

    /** @param callable(object): void $listener */
    public function on(string $eventClass, callable $listener): static
    {
        $this->hooks->addListener($eventClass, $listener);

        return $this;
    }

    public function off(string $eventClass, callable $listener): static
    {
        $this->hooks->removeListener($eventClass, $listener);

        return $this;
    }
}
