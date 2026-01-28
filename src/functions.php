<?php

declare(strict_types=1);

namespace LlmExe;

use LlmExe\Embeddings\EmbeddingProviderInterface;
use LlmExe\Executor\CallableExecutor;
use LlmExe\Executor\CoreExecutor;
use LlmExe\Executor\LlmExecutor;
use LlmExe\Executor\LlmExecutorWithFunctions;
use LlmExe\Executor\UseExecutors;
use LlmExe\Parser\ParserInterface;
use LlmExe\Prompt\ChatPrompt;
use LlmExe\Prompt\TextPrompt;
use LlmExe\Provider\LlmProviderInterface;
use LlmExe\State\ConversationState;
use LlmExe\State\Dialogue;

/**
 * Create an LLM provider instance.
 *
 * @param  array<string, mixed>  $options
 */
function useLlm(string $provider, array $options = []): LlmProviderInterface
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
        default => Factory::createChatPrompt(),
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
 * @param  array<string, mixed>  $options
 */
function createLlmExecutor(array $options): LlmExecutor
{
    return Factory::createLlmExecutor($options);
}

/**
 * Create an LLM executor with function calling support.
 *
 * @param  array<string, mixed>  $options
 */
function createLlmExecutorWithFunctions(array $options): LlmExecutorWithFunctions
{
    return Factory::createLlmExecutorWithFunctions($options);
}

/**
 * Create a tool registry from executors.
 *
 * @param  list<CallableExecutor|array<string, mixed>>  $executors
 */
function useExecutors(array $executors): UseExecutors
{
    return Factory::useExecutors($executors);
}

/**
 * Create a callable executor from config.
 *
 * @param  array<string, mixed>  $config
 */
function createCallableExecutor(array $config): CallableExecutor
{
    return Factory::createCallableExecutor($config);
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

/**
 * Create an embedding provider instance.
 *
 * @param  array<string, mixed>  $options
 */
function useEmbeddings(string $provider, array $options = []): EmbeddingProviderInterface
{
    return Factory::useEmbeddings($provider, $options);
}
