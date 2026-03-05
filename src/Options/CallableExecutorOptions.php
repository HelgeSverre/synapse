<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Options;

use HelgeSverre\Synapse\State\ConversationState;

final readonly class CallableExecutorOptions
{
    public string $name;

    public string $description;

    /** @var \Closure(array<string, mixed>, ConversationState): mixed */
    public \Closure $handler;

    /** @var array<string, mixed> */
    public array $parameters;

    /** @var array<string, mixed> */
    public array $attributes;

    /** @var (\Closure(array<string, mixed>, ConversationState): bool)|null */
    public ?\Closure $visibilityHandler;

    /** @var (\Closure(array<string, mixed>): array{valid: bool, errors: list<string>})|null */
    public ?\Closure $validateInputHandler;

    /**
     * @param  callable(array<string, mixed>, ConversationState): mixed  $handler
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $attributes
     * @param  callable(array<string, mixed>, ConversationState): bool|null  $visibilityHandler
     * @param  callable(array<string, mixed>): array{valid: bool, errors: list<string>}|null  $validateInputHandler
     */
    public function __construct(
        string $name,
        string $description,
        callable $handler,
        array $parameters = [],
        array $attributes = [],
        ?callable $visibilityHandler = null,
        ?callable $validateInputHandler = null,
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->handler = \Closure::fromCallable($handler);
        $this->parameters = $parameters;
        $this->attributes = $attributes;
        $this->visibilityHandler = $visibilityHandler !== null ? \Closure::fromCallable($visibilityHandler) : null;
        $this->validateInputHandler = $validateInputHandler !== null ? \Closure::fromCallable($validateInputHandler) : null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            name: $config['name'] ?? throw new \InvalidArgumentException('name is required'),
            description: $config['description'] ?? throw new \InvalidArgumentException('description is required'),
            handler: $config['handler'] ?? throw new \InvalidArgumentException('handler is required'),
            parameters: $config['parameters'] ?? [],
            attributes: $config['attributes'] ?? [],
            visibilityHandler: $config['visibilityHandler'] ?? null,
            validateInputHandler: $config['validateInput'] ?? null,
        );
    }
}
