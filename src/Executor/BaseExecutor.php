<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Executor;

use HelgeSverre\Synapse\Hooks\Events\OnComplete;
use HelgeSverre\Synapse\Hooks\Events\OnError;
use HelgeSverre\Synapse\Hooks\Events\OnSuccess;
use HelgeSverre\Synapse\Hooks\HookDispatcher;
use HelgeSverre\Synapse\Hooks\HookDispatcherInterface;
use HelgeSverre\Synapse\State\ConversationState;

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
     * @param  list<\HelgeSverre\Synapse\State\Message>  $history
     * @return ExecutionResult<O>
     */
    public function execute(array $input = [], array $history = []): ExecutionResult
    {
        $start = hrtime(true);
        $this->metadata = $this->metadata->withExecution();

        try {
            if ($history !== []) {
                $input['history'] = $history;
            }

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
     * @param  I  $input
     * @param  list<\HelgeSverre\Synapse\State\Message>  $history
     * @return ExecutionResult<O>
     */
    public function run(array $input = [], array $history = []): ExecutionResult
    {
        return $this->execute($input, $history);
    }

    /**
     * Transform input before handler.
     *
     * @param  array<string, mixed>  $input
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

    /**
     * @template TEvent of object
     *
     * @param  class-string<TEvent>  $eventClass
     * @param  callable(TEvent): void  $listener
     */
    public function on(string $eventClass, callable $listener): static
    {
        $this->hooks->addListener($eventClass, $listener);

        return $this;
    }

    /**
     * @template TEvent of object
     *
     * @param  class-string<TEvent>  $eventClass
     * @param  callable(TEvent): void  $listener
     */
    public function off(string $eventClass, callable $listener): static
    {
        $this->hooks->removeListener($eventClass, $listener);

        return $this;
    }
}
