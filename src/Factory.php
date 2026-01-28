<?php

declare(strict_types=1);

namespace LlmExe;

use LlmExe\Embeddings\Cohere\CohereEmbeddingProvider;
use LlmExe\Embeddings\EmbeddingProviderInterface;
use LlmExe\Embeddings\Jina\JinaEmbeddingProvider;
use LlmExe\Embeddings\Mistral\MistralEmbeddingProvider;
use LlmExe\Embeddings\OpenAI\OpenAIEmbeddingProvider;
use LlmExe\Embeddings\Voyage\VoyageEmbeddingProvider;
use LlmExe\Executor\CallableExecutor;
use LlmExe\Executor\CoreExecutor;
use LlmExe\Executor\LlmExecutor;
use LlmExe\Executor\LlmExecutorWithFunctions;
use LlmExe\Executor\UseExecutors;
use LlmExe\Parser\BooleanParser;
use LlmExe\Parser\CustomParser;
use LlmExe\Parser\EnumParser;
use LlmExe\Parser\JsonParser;
use LlmExe\Parser\ListParser;
use LlmExe\Parser\ListToJsonParser;
use LlmExe\Parser\ListToKeyValueParser;
use LlmExe\Parser\LlmFunctionParser;
use LlmExe\Parser\MarkdownCodeBlockParser;
use LlmExe\Parser\MarkdownCodeBlocksParser;
use LlmExe\Parser\NumberParser;
use LlmExe\Parser\ParserInterface;
use LlmExe\Parser\ReplaceStringTemplateParser;
use LlmExe\Parser\StringParser;
use LlmExe\Prompt\ChatPrompt;
use LlmExe\Prompt\TextPrompt;
use LlmExe\Provider\Anthropic\AnthropicProvider;
use LlmExe\Provider\Google\GoogleProvider;
use LlmExe\Provider\Http\Psr18Transport;
use LlmExe\Provider\Http\TransportInterface;
use LlmExe\Provider\LlmProviderInterface;
use LlmExe\Provider\Mistral\MistralProvider;
use LlmExe\Provider\OpenAI\OpenAIProvider;
use LlmExe\Provider\XAI\XAIProvider;
use LlmExe\State\ConversationState;
use LlmExe\State\Dialogue;
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
        if (! self::$defaultTransport instanceof \LlmExe\Provider\Http\TransportInterface) {
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

    public static function useLlm(string $provider, array $options = []): LlmProviderInterface
    {
        $transport = $options['transport'] ?? self::getDefaultTransport();

        [$providerName, $model] = explode('.', $provider, 2) + [null, null];

        return match ($providerName) {
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
            default => throw new \InvalidArgumentException("Unknown provider: {$providerName}"),
        };
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
            'string' => new StringParser($options['trim'] ?? true),
            'boolean', 'bool' => new BooleanParser,
            'number', 'int', 'float' => new NumberParser($options['intOnly'] ?? false),
            'json' => new JsonParser(
                schema: $options['schema'] ?? null,
                validateSchema: $options['validateSchema'] ?? false,
                validator: $options['validator'] ?? null,
            ),
            'list', 'array' => new ListParser,
            'code', 'codeblock', 'markdownCodeBlock' => new MarkdownCodeBlockParser(
                language: $options['language'] ?? null,
                firstOnly: $options['firstOnly'] ?? true,
            ),
            'codeblocks', 'markdownCodeBlocks' => new MarkdownCodeBlocksParser,
            'enum' => new EnumParser(
                allowedValues: $options['values'] ?? throw new \InvalidArgumentException('values is required'),
                caseSensitive: $options['caseSensitive'] ?? false,
            ),
            'keyvalue', 'key-value' => new ListToKeyValueParser(
                separator: $options['separator'] ?? ':',
                trimValues: $options['trimValues'] ?? true,
            ),
            'listjson', 'list-json' => new ListToJsonParser(
                separator: $options['separator'] ?? ':',
                indentSpaces: $options['indentSpaces'] ?? 2,
            ),
            'template', 'replace' => (new ReplaceStringTemplateParser(
                strict: $options['strict'] ?? false,
            ))->withReplacements($options['replacements'] ?? []),
            'function', 'tool' => new LlmFunctionParser(
                wrappedParser: $options['parser'] ?? new StringParser,
            ),
            'custom' => new CustomParser(
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
     * @param  array<string, mixed>  $options
     */
    public static function createLlmExecutor(array $options): LlmExecutor
    {
        return new LlmExecutor(
            provider: $options['llm'] ?? $options['provider'] ?? throw new \InvalidArgumentException('llm/provider is required'),
            prompt: $options['prompt'] ?? throw new \InvalidArgumentException('prompt is required'),
            parser: $options['parser'] ?? new StringParser,
            model: $options['model'] ?? throw new \InvalidArgumentException('model is required'),
            temperature: $options['temperature'] ?? null,
            maxTokens: $options['maxTokens'] ?? null,
            name: $options['name'] ?? null,
            hooks: $options['hooks'] ?? null,
            state: $options['state'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function createLlmExecutorWithFunctions(array $options): LlmExecutorWithFunctions
    {
        $tools = $options['tools'] ?? null;
        if (! $tools instanceof UseExecutors) {
            if (is_array($tools)) {
                /** @var list<CallableExecutor|array<string, mixed>> $tools */
                $tools = self::useExecutors($tools);
            } else {
                throw new \InvalidArgumentException('tools must be UseExecutors or array of CallableExecutor');
            }
        }

        return new LlmExecutorWithFunctions(
            provider: $options['llm'] ?? $options['provider'] ?? throw new \InvalidArgumentException('llm/provider is required'),
            prompt: $options['prompt'] ?? throw new \InvalidArgumentException('prompt is required'),
            parser: $options['parser'] ?? new StringParser,
            model: $options['model'] ?? throw new \InvalidArgumentException('model is required'),
            tools: $tools,
            maxIterations: $options['maxIterations'] ?? 10,
            temperature: $options['temperature'] ?? null,
            maxTokens: $options['maxTokens'] ?? null,
            name: $options['name'] ?? null,
            hooks: $options['hooks'] ?? null,
            state: $options['state'] ?? null,
        );
    }

    /**
     * @param  list<CallableExecutor|array<string, mixed>>  $executors
     */
    public static function useExecutors(array $executors): UseExecutors
    {
        $callables = [];

        foreach ($executors as $executor) {
            if ($executor instanceof CallableExecutor) {
                $callables[] = $executor;
            } elseif (is_array($executor)) {
                $callables[] = self::createCallableExecutor($executor);
            }
        }

        return new UseExecutors($callables);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function createCallableExecutor(array $config): CallableExecutor
    {
        return new CallableExecutor(
            name: $config['name'] ?? throw new \InvalidArgumentException('name is required'),
            description: $config['description'] ?? throw new \InvalidArgumentException('description is required'),
            handler: $config['handler'] ?? throw new \InvalidArgumentException('handler is required'),
            parameters: $config['parameters'] ?? [],
            attributes: $config['attributes'] ?? [],
            visibilityHandler: $config['visibilityHandler'] ?? null,
            validateInputHandler: $config['validateInput'] ?? null,
        );
    }

    public static function createState(): ConversationState
    {
        return new ConversationState;
    }

    public static function createDialogue(?string $name = null): Dialogue
    {
        return new Dialogue($name ?? 'default');
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
