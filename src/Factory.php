<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse;

use HelgeSverre\Synapse\Agent\AgentRegistry;
use HelgeSverre\Synapse\Embeddings\Cohere\CohereEmbeddingProvider;
use HelgeSverre\Synapse\Embeddings\EmbeddingProviderInterface;
use HelgeSverre\Synapse\Embeddings\Jina\JinaEmbeddingProvider;
use HelgeSverre\Synapse\Embeddings\Mistral\MistralEmbeddingProvider;
use HelgeSverre\Synapse\Embeddings\OpenAI\OpenAIEmbeddingProvider;
use HelgeSverre\Synapse\Embeddings\Voyage\VoyageEmbeddingProvider;
use HelgeSverre\Synapse\Executor\CallableExecutor;
use HelgeSverre\Synapse\Executor\CoreExecutor;
use HelgeSverre\Synapse\Executor\LlmExecutor;
use HelgeSverre\Synapse\Executor\LlmExecutorWithFunctions;
use HelgeSverre\Synapse\Executor\MiddlewareToolExecutor;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutor;
use HelgeSverre\Synapse\Executor\StreamingLlmExecutorWithFunctions;
use HelgeSverre\Synapse\Executor\ToolExecutorInterface;
use HelgeSverre\Synapse\Executor\ToolMiddleware;
use HelgeSverre\Synapse\Executor\ToolRegistry;
use HelgeSverre\Synapse\Options\CallableExecutorOptions;
use HelgeSverre\Synapse\Options\ExecutorOptions;
use HelgeSverre\Synapse\Parser\ParserInterface;
use HelgeSverre\Synapse\Parser\Parsers;
use HelgeSverre\Synapse\Parser\StringParser;
use HelgeSverre\Synapse\Prompt\ChatPrompt;
use HelgeSverre\Synapse\Prompt\TextPrompt;
use HelgeSverre\Synapse\Provider\Anthropic\AnthropicProvider;
use HelgeSverre\Synapse\Provider\Google\GoogleProvider;
use HelgeSverre\Synapse\Provider\Groq\GroqProvider;
use HelgeSverre\Synapse\Provider\Http\Psr18Transport;
use HelgeSverre\Synapse\Provider\Http\TransportInterface;
use HelgeSverre\Synapse\Provider\LlmProviderInterface;
use HelgeSverre\Synapse\Provider\Mistral\MistralProvider;
use HelgeSverre\Synapse\Provider\Moonshot\MoonshotProvider;
use HelgeSverre\Synapse\Provider\OpenAI\OpenAIProvider;
use HelgeSverre\Synapse\Provider\XAI\XAIProvider;
use HelgeSverre\Synapse\State\ConversationState;
use HelgeSverre\Synapse\State\Dialogue;
use HelgeSverre\Synapse\Streaming\StreamableProviderInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class Factory
{
    private static ?TransportInterface $defaultTransport = null;

    public static function setDefaultTransport(TransportInterface $transport): void
    {
        self::$defaultTransport = $transport;
    }

    public static function getDefaultTransport(): TransportInterface
    {
        if (! self::$defaultTransport instanceof \HelgeSverre\Synapse\Provider\Http\TransportInterface) {
            self::$defaultTransport = self::discoverTransport();
        }

        return self::$defaultTransport;
    }

    private static function discoverTransport(): TransportInterface
    {
        // Try Guzzle (most common)
        if (class_exists(\GuzzleHttp\Client::class)) {
            $client = new \GuzzleHttp\Client;

            return new Psr18Transport($client, new \GuzzleHttp\Psr7\HttpFactory, new \GuzzleHttp\Psr7\HttpFactory);
        }

        // Try Symfony HTTP Client (Psr18Client implements all three PSR interfaces)
        if (class_exists(\Symfony\Component\HttpClient\Psr18Client::class)) {
            /** @var ClientInterface&RequestFactoryInterface&StreamFactoryInterface $client */
            $client = new \Symfony\Component\HttpClient\Psr18Client;

            return new Psr18Transport($client, $client, $client);
        }

        throw new \RuntimeException(
            'No HTTP client found. Install guzzlehttp/guzzle or symfony/http-client, '.
            'or call Factory::setDefaultTransport() with a custom transport.',
        );
    }

    public static function createTransport(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
    ): TransportInterface {
        return new Psr18Transport($client, $requestFactory, $streamFactory);
    }

    public static function useLlm(string $provider, array $options = []): Llm
    {
        $transport = $options['transport'] ?? self::getDefaultTransport();

        $parts = explode('.', $provider, 2);
        $providerName = $parts[0];
        $modelFromProvider = $parts[1] ?? null;
        $modelFromOptions = $options['model'] ?? null;

        if ($modelFromProvider !== null && $modelFromOptions !== null && $modelFromProvider !== $modelFromOptions) {
            throw new \InvalidArgumentException('Model is configured twice. Use either provider.model or options["model"], not conflicting values.');
        }

        $model = $modelFromOptions ?? $modelFromProvider;

        $providerInstance = match ($providerName) {
            'openai' => new OpenAIProvider(
                transport: $transport,
                apiKey: $options['apiKey'] ?? throw new \InvalidArgumentException('apiKey is required'),
                baseUrl: $options['baseUrl'] ?? 'https://api.openai.com/v1',
            ),
            'anthropic' => new AnthropicProvider(
                transport: $transport,
                apiKey: $options['apiKey'] ?? throw new \InvalidArgumentException('apiKey is required'),
                baseUrl: $options['baseUrl'] ?? 'https://api.anthropic.com/v1',
            ),
            'google', 'gemini' => new GoogleProvider(
                transport: $transport,
                apiKey: $options['apiKey'] ?? throw new \InvalidArgumentException('apiKey is required'),
                baseUrl: $options['baseUrl'] ?? 'https://generativelanguage.googleapis.com/v1beta',
            ),
            'xai', 'grok' => new XAIProvider(
                transport: $transport,
                apiKey: $options['apiKey'] ?? throw new \InvalidArgumentException('apiKey is required'),
                baseUrl: $options['baseUrl'] ?? 'https://api.x.ai/v1',
            ),
            'mistral' => new MistralProvider(
                transport: $transport,
                apiKey: $options['apiKey'] ?? throw new \InvalidArgumentException('apiKey is required'),
                baseUrl: $options['baseUrl'] ?? 'https://api.mistral.ai/v1',
            ),
            'groq' => new GroqProvider(
                transport: $transport,
                apiKey: $options['apiKey'] ?? throw new \InvalidArgumentException('apiKey is required'),
                baseUrl: $options['baseUrl'] ?? 'https://api.groq.com/openai/v1',
            ),
            'moonshot' => new MoonshotProvider(
                transport: $transport,
                apiKey: $options['apiKey'] ?? throw new \InvalidArgumentException('apiKey is required'),
                baseUrl: $options['baseUrl'] ?? 'https://api.moonshot.ai/v1',
            ),
            default => throw new \InvalidArgumentException("Unknown provider: {$providerName}"),
        };

        return new Llm($providerInstance, $model);
    }

    /**
     * Resolve the provider and model from executor options.
     *
     * Unwraps Llm instances to extract the inner provider and default model.
     * Explicit 'model' option always takes precedence over the Llm default.
     *
     * @return array{0: LlmProviderInterface, 1: string}
     */
    private static function resolveProviderAndModel(ExecutorOptions $options): array
    {
        $llm = $options->llm;

        if ($llm instanceof Llm) {
            $provider = $llm->provider;
            if ($options->model !== null && $llm->model !== null && $options->model !== $llm->model) {
                throw new \InvalidArgumentException('Model is configured twice (useLlm + executor options). Remove one source of truth.');
            }

            $model = $options->model ?? $llm->model ?? throw new \InvalidArgumentException('model is required');
        } else {
            $provider = $llm;
            $model = $options->model ?? throw new \InvalidArgumentException('model is required');
        }

        return [$provider, $model];
    }

    /**
     * @param  array<string, mixed>|ExecutorOptions  $options
     */
    private static function normalizeExecutorOptions(array|ExecutorOptions $options): ExecutorOptions
    {
        if ($options instanceof ExecutorOptions) {
            return $options;
        }

        return ExecutorOptions::fromArray($options);
    }

    public static function createTextPrompt(): TextPrompt
    {
        return new TextPrompt;
    }

    public static function createChatPrompt(): ChatPrompt
    {
        return new ChatPrompt;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function createParser(string $type, array $options = []): ParserInterface
    {
        return match ($type) {
            'string' => Parsers::string($options['trim'] ?? true),
            'boolean', 'bool' => Parsers::boolean(),
            'number', 'int', 'float' => Parsers::number($options['intOnly'] ?? false),
            'json' => Parsers::json(
                schema: $options['schema'] ?? null,
                validateSchema: $options['validateSchema'] ?? false,
                validator: $options['validator'] ?? null,
            ),
            'list', 'array' => Parsers::list(),
            'code', 'codeblock', 'markdownCodeBlock' => Parsers::codeBlock(
                language: $options['language'] ?? null,
                firstOnly: $options['firstOnly'] ?? true,
            ),
            'codeblocks', 'markdownCodeBlocks' => Parsers::codeBlocks(),
            'enum' => Parsers::enum(
                values: $options['values'] ?? throw new \InvalidArgumentException('values is required'),
                caseSensitive: $options['caseSensitive'] ?? false,
            ),
            'keyvalue', 'key-value' => Parsers::keyValue(
                separator: $options['separator'] ?? ':',
                trimValues: $options['trimValues'] ?? true,
            ),
            'listjson', 'list-json' => Parsers::listJson(
                separator: $options['separator'] ?? ':',
                indentSpaces: $options['indentSpaces'] ?? 2,
            ),
            'template', 'replace' => Parsers::template(
                replacements: $options['replacements'] ?? [],
                strict: $options['strict'] ?? false,
            ),
            'function', 'tool' => Parsers::tool(
                wrappedParser: $options['parser'] ?? null,
            ),
            'custom' => Parsers::custom(
                handler: $options['handler'] ?? throw new \InvalidArgumentException('handler is required'),
            ),
            default => throw new \InvalidArgumentException("Unknown parser type: {$type}"),
        };
    }

    /**
     * @template T
     *
     * @param  callable(array<string, mixed>): T  $handler
     * @return CoreExecutor<array<string, mixed>, T>
     */
    public static function createCoreExecutor(callable $handler, ?string $name = null): CoreExecutor
    {
        return new CoreExecutor($handler, $name);
    }

    /**
     * @param  array<string, mixed>|ExecutorOptions  $options
     */
    public static function createLlmExecutor(array|ExecutorOptions $options): LlmExecutor
    {
        $resolved = self::normalizeExecutorOptions($options);
        [$provider, $model] = self::resolveProviderAndModel($resolved);

        return new LlmExecutor(
            provider: $provider,
            prompt: $resolved->prompt,
            parser: $resolved->parser ?? new StringParser,
            model: $model,
            temperature: $resolved->temperature,
            maxTokens: $resolved->maxTokens,
            responseFormat: $resolved->responseFormat,
            name: $resolved->name,
            hooks: $resolved->hooks,
            state: $resolved->state,
        );
    }

    /**
     * @param  array<string, mixed>|ExecutorOptions  $options
     */
    public static function createLlmExecutorWithFunctions(array|ExecutorOptions $options): LlmExecutorWithFunctions
    {
        $resolved = self::normalizeExecutorOptions($options);
        [$provider, $model] = self::resolveProviderAndModel($resolved);

        $tools = $resolved->tools;
        if (! $tools instanceof ToolExecutorInterface) {
            if (is_array($tools)) {
                if (! array_is_list($tools)) {
                    throw new \InvalidArgumentException('tools array must be a list of CallableExecutor instances or config arrays');
                }

                /** @var list<CallableExecutor|array<string, mixed>> $toolList */
                $toolList = $tools;
                $tools = self::createToolRegistry($toolList);
            } else {
                throw new \InvalidArgumentException('tools must implement ToolExecutorInterface or be an array of CallableExecutor configs');
            }
        }

        return new LlmExecutorWithFunctions(
            provider: $provider,
            prompt: $resolved->prompt,
            parser: $resolved->parser ?? new StringParser,
            model: $model,
            tools: $tools,
            maxIterations: $resolved->maxIterations,
            toolCatalogResolver: $resolved->toolCatalogResolver,
            temperature: $resolved->temperature,
            maxTokens: $resolved->maxTokens,
            name: $resolved->name,
            hooks: $resolved->hooks,
            state: $resolved->state,
        );
    }

    /**
     * @param  array<string, mixed>|ExecutorOptions  $options
     */
    public static function createStreamingLlmExecutor(array|ExecutorOptions $options): StreamingLlmExecutor
    {
        $resolved = self::normalizeExecutorOptions($options);
        [$provider, $model] = self::resolveProviderAndModel($resolved);

        if (! $provider instanceof StreamableProviderInterface) {
            throw new \InvalidArgumentException('Provider must implement StreamableProviderInterface for streaming');
        }

        return new StreamingLlmExecutor(
            provider: $provider,
            prompt: $resolved->prompt,
            model: $model,
            temperature: $resolved->temperature,
            maxTokens: $resolved->maxTokens,
            name: $resolved->name,
            hooks: $resolved->hooks,
            state: $resolved->state,
        );
    }

    /**
     * @param  array<string, mixed>|ExecutorOptions  $options
     */
    public static function createStreamingLlmExecutorWithFunctions(array|ExecutorOptions $options): StreamingLlmExecutorWithFunctions
    {
        $resolved = self::normalizeExecutorOptions($options);
        [$provider, $model] = self::resolveProviderAndModel($resolved);

        if (! $provider instanceof StreamableProviderInterface) {
            throw new \InvalidArgumentException('Provider must implement StreamableProviderInterface for streaming');
        }

        $tools = $resolved->tools;
        if (! $tools instanceof ToolExecutorInterface) {
            if (is_array($tools)) {
                if (! array_is_list($tools)) {
                    throw new \InvalidArgumentException('tools array must be a list of CallableExecutor instances or config arrays');
                }

                /** @var list<CallableExecutor|array<string, mixed>> $toolList */
                $toolList = $tools;
                $tools = self::createToolRegistry($toolList);
            } else {
                throw new \InvalidArgumentException('tools must implement ToolExecutorInterface or be an array of CallableExecutor configs');
            }
        }

        return new StreamingLlmExecutorWithFunctions(
            provider: $provider,
            prompt: $resolved->prompt,
            model: $model,
            tools: $tools,
            maxIterations: $resolved->maxIterations,
            toolCatalogResolver: $resolved->toolCatalogResolver,
            temperature: $resolved->temperature,
            maxTokens: $resolved->maxTokens,
            name: $resolved->name,
            hooks: $resolved->hooks,
            state: $resolved->state,
        );
    }

    /**
     * @param  array<string, mixed>|ExecutorOptions  $options
     */
    public static function createExecutor(array|ExecutorOptions $options): LlmExecutor|LlmExecutorWithFunctions|StreamingLlmExecutor|StreamingLlmExecutorWithFunctions
    {
        $resolved = self::normalizeExecutorOptions($options);
        $hasTools = $resolved->tools !== null;

        if ($resolved->stream && $hasTools) {
            return self::createStreamingLlmExecutorWithFunctions($resolved);
        }

        if ($resolved->stream) {
            return self::createStreamingLlmExecutor($resolved);
        }

        if ($hasTools) {
            return self::createLlmExecutorWithFunctions($resolved);
        }

        return self::createLlmExecutor($resolved);
    }

    /**
     * @param  list<mixed>  $executors
     */
    public static function createToolRegistry(array $executors): ToolRegistry
    {
        $callables = [];

        foreach ($executors as $executor) {
            if ($executor instanceof CallableExecutor) {
                $callables[] = $executor;
            } elseif ($executor instanceof CallableExecutorOptions) {
                $callables[] = self::createCallableExecutor($executor);
            } elseif (is_array($executor)) {
                $callables[] = self::createCallableExecutor($executor);
            } else {
                throw new \InvalidArgumentException('Executor must be CallableExecutor, CallableExecutorOptions, or config array');
            }
        }

        return new ToolRegistry($callables);
    }

    /**
     * @param  array<string, mixed>|CallableExecutorOptions  $config
     */
    public static function createCallableExecutor(array|CallableExecutorOptions $config): CallableExecutor
    {
        $options = $config instanceof CallableExecutorOptions ? $config : CallableExecutorOptions::fromArray($config);

        return new CallableExecutor(
            name: $options->name,
            description: $options->description,
            handler: $options->handler,
            parameters: $options->parameters,
            attributes: $options->attributes,
            visibilityHandler: $options->visibilityHandler,
            validateInputHandler: $options->validateInputHandler,
        );
    }

    /**
     * @param  list<ToolMiddleware>  $middleware
     */
    public static function createMiddlewareToolExecutor(
        ToolExecutorInterface $inner,
        array $middleware = [],
    ): MiddlewareToolExecutor {
        return new MiddlewareToolExecutor($inner, $middleware);
    }

    public static function createState(): ConversationState
    {
        return new ConversationState;
    }

    public static function createDialogue(?string $name = null): Dialogue
    {
        return new Dialogue($name ?? 'default');
    }

    public static function createAgentRegistry(): AgentRegistry
    {
        return new AgentRegistry;
    }

    /**
     * Create an embedding provider instance.
     *
     * @param  array<string, mixed>  $options
     */
    public static function useEmbeddings(string $provider, array $options = []): EmbeddingProviderInterface
    {
        $transport = $options['transport'] ?? self::getDefaultTransport();

        return match ($provider) {
            'openai' => new OpenAIEmbeddingProvider(
                transport: $transport,
                apiKey: $options['apiKey'] ?? throw new \InvalidArgumentException('apiKey is required'),
                baseUrl: $options['baseUrl'] ?? 'https://api.openai.com/v1',
            ),
            'mistral' => new MistralEmbeddingProvider(
                transport: $transport,
                apiKey: $options['apiKey'] ?? throw new \InvalidArgumentException('apiKey is required'),
                baseUrl: $options['baseUrl'] ?? 'https://api.mistral.ai/v1',
            ),
            'voyage' => new VoyageEmbeddingProvider(
                transport: $transport,
                apiKey: $options['apiKey'] ?? throw new \InvalidArgumentException('apiKey is required'),
                baseUrl: $options['baseUrl'] ?? 'https://api.voyageai.com/v1',
            ),
            'cohere' => new CohereEmbeddingProvider(
                transport: $transport,
                apiKey: $options['apiKey'] ?? throw new \InvalidArgumentException('apiKey is required'),
                baseUrl: $options['baseUrl'] ?? 'https://api.cohere.com/v2',
            ),
            'jina' => new JinaEmbeddingProvider(
                transport: $transport,
                apiKey: $options['apiKey'] ?? throw new \InvalidArgumentException('apiKey is required'),
                baseUrl: $options['baseUrl'] ?? 'https://api.jina.ai/v1',
            ),
            default => throw new \InvalidArgumentException("Unknown embedding provider: {$provider}"),
        };
    }
}
