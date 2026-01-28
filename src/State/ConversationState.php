<?php

declare(strict_types=1);

namespace LlmExe\State;

final readonly class ConversationState
{
    /**
     * @param  list<Message>  $messages
     * @param  array<string, ContextItem>  $context
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $messages = [],
        public array $context = [],
        public array $attributes = [],
    ) {}

    public function withMessage(Message $message): self
    {
        return new self(
            [...$this->messages, $message],
            $this->context,
            $this->attributes,
        );
    }

    /** @param list<Message> $messages */
    public function withMessages(array $messages): self
    {
        return new self(
            [...$this->messages, ...$messages],
            $this->context,
            $this->attributes,
        );
    }

    public function withContext(ContextItem $item): self
    {
        $context = $this->context;
        $context[$item->key] = $item;

        return new self($this->messages, $context, $this->attributes);
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$key] = $value;

        return new self($this->messages, $this->context, $attributes);
    }

    public function getContext(string $key): ?ContextItem
    {
        return $this->context[$key] ?? null;
    }

    public function getContextValue(string $key, mixed $default = null): mixed
    {
        $item = $this->context[$key] ?? null;

        return $item !== null ? $item->value : $default;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /** @return list<Message> */
    public function getMessagesByRole(Role $role): array
    {
        return array_values(
            array_filter($this->messages, fn (Message $m): bool => $m->role === $role),
        );
    }

    public function getLastMessage(): ?Message
    {
        return $this->messages[count($this->messages) - 1] ?? null;
    }

    public function clear(): self
    {
        return new self([], $this->context, $this->attributes);
    }
}
