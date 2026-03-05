<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse;

use HelgeSverre\Synapse\Agent\AgentRegistry;
use HelgeSverre\Synapse\Embeddings\EmbeddingProviderInterface;
use HelgeSverre\Synapse\Executor\CallableExecutor;
use HelgeSverre\Synapse\Executor\CoreExecutor;
use HelgeSverre\Synapse\Executor\LlmExecutor;
use HelgeSverre\Synapse\Executor\LlmExecutorWithFunctions;
use HelgeSverre\Synapse\Executor\MiddlewareToolExecutor;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutor;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutorWithFunctions;
use HelgeSverre\Synapse\Executor\ToolMiddleware;
use HelgeSverre\Synapse\Executor\ToolRegistry;
use HelgeSverre\Synapse\Options\CallableExecutorOptions;
use HelgeSverre\Synapse\Options\ExecutorOptions;
use HelgeSverre\Synapse\Parser\ParserInterface;
use HelgeSverre\Synapse\Prompt\ChatPrompt;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\State\ConversationState;
use HelgeSverre\Synapse\State\Dialogue;

/**
 * Create an LLM provider instance.
 *
 * @param  array<string, mixed>  $options
 */
function useLlm(string $provider, array $options = []): Llm
{
    return Factory::useLlm($provider, $options);
}

/**
 * Create a text prompt.
 */
function createTextPrompt(): TextPrompt
{
    return Factory::createTextPrompt();
}

/**
 * Create a chat prompt.
 */
function createChatPrompt(): ChatPrompt
{
    return Factory::createChatPrompt();
}

/**
 * Alias for createChatPrompt().
 */
function createPrompt(string $type = 'chat'): TextPrompt|ChatPrompt
{
    return match ($type) {
        'text' => Factory::createTextPrompt(),
        'chat' => Factory::createChatPrompt(),
        default => throw new \InvalidArgumentException("Unknown prompt type: {$type}. Expected 'chat' or 'text'."),
    };
}

/**
 * Create a parser.
 *
 * @param  array<string, mixed>  $options
 */
function createParser(string $type, array $options = []): ParserInterface
{
    return Factory::createParser($type, $options);
}

/**
 * Create a core executor from a callable.
 *
 * @template T
 *
 * @param  callable(array<string, mixed>): T  $handler
 * @return CoreExecutor<array<string, mixed>, T>
 */
function createCoreExecutor(callable $handler, ?string $name = null): CoreExecutor
{
    return Factory::createCoreExecutor($handler, $name);
}

/**
 * Create an LLM executor.
 *
 * @param  array<string, mixed>|ExecutorOptions  $options
 */
function createLlmExecutor(array|ExecutorOptions $options): LlmExecutor
{
    return Factory::createLlmExecutor($options);
}

/**
 * Create an LLM executor with function calling support.
 *
 * @param  array<string, mixed>|ExecutorOptions  $options
 */
function createLlmExecutorWithFunctions(array|ExecutorOptions $options): LlmExecutorWithFunctions
{
    return Factory::createLlmExecutorWithFunctions($options);
}

/**
 * Create a streaming LLM executor.
 *
 * @param  array<string, mixed>|ExecutorOptions  $options
 */
function createStreamingLlmExecutor(array|ExecutorOptions $options): StreamingLlmExecutor
{
    return Factory::createStreamingLlmExecutor($options);
}

/**
 * Create a streaming LLM executor with function calling support.
 *
 * @param  array<string, mixed>|ExecutorOptions  $options
 */
function createStreamingLlmExecutorWithFunctions(array|ExecutorOptions $options): StreamingLlmExecutorWithFunctions
{
    return Factory::createStreamingLlmExecutorWithFunctions($options);
}

/**
 * Create an executor using a single, predictable API.
 *
 * - `stream: false` + no tools => LlmExecutor
 * - `stream: false` + tools    => LlmExecutorWithFunctions
 * - `stream: true`  + no tools => StreamingLlmExecutor
 * - `stream: true`  + tools    => StreamingLlmExecutorWithFunctions
 *
 * @param  array<string, mixed>|ExecutorOptions  $options
 */
function createExecutor(array|ExecutorOptions $options): LlmExecutor|LlmExecutorWithFunctions|StreamingLlmExecutor|StreamingLlmExecutorWithFunctions
{
    return Factory::createExecutor($options);
}

/**
 * Create a tool registry from executors.
 *
 * @param  list<CallableExecutor|CallableExecutorOptions|array<string, mixed>>  $executors
 */
function createToolRegistry(array $executors): ToolRegistry
{
    return Factory::createToolRegistry($executors);
}

/**
 * Create a callable executor from config.
 *
 * @param  array<string, mixed>|CallableExecutorOptions  $config
 */
function createCallableExecutor(array|CallableExecutorOptions $config): CallableExecutor
{
    return Factory::createCallableExecutor($config);
}

/**
 * @param  list<ToolMiddleware>  $middleware
 */
function createMiddlewareToolExecutor(
    \HelgeSverre\Synapse\Executor\ToolExecutorInterface $inner,
    array $middleware = [],
): MiddlewareToolExecutor {
    return Factory::createMiddlewareToolExecutor($inner, $middleware);
}

/**
 * Create an empty conversation state.
 */
function createState(): ConversationState
{
    return Factory::createState();
}

/**
 * Create a dialogue for managing conversation history.
 */
function createDialogue(?string $name = null): Dialogue
{
    return Factory::createDialogue($name);
}

function createAgentRegistry(): AgentRegistry
{
    return Factory::createAgentRegistry();
}

/**
 * Create an embedding provider instance.
 *
 * @param  array<string, mixed>  $options
 */
function useEmbeddings(string $provider, array $options = []): EmbeddingProviderInterface
{
    return Factory::useEmbeddings($provider, $options);
}
