<?php

declare(strict_types=1);

namespace LlmExe\State;

use LlmExe\Provider\Response\GenerationResponse;

/**
 * Manages conversation history for multi-turn dialogues.
 * Provides a fluent interface for building message history.
 */
final class Dialogue
{
    /** @var list<Message> */
    private array $messages = [];

    public function __construct(
        private readonly string $name = 'default',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function setUserMessage(string $content, ?string $name = null): self
    {
        $this->messages[] = Message::user($content, $name);

        return $this;
    }

    public function setAssistantMessage(string $content): self
    {
        $this->messages[] = Message::assistant($content);

        return $this;
    }

    public function setSystemMessage(string $content): self
    {
        $this->messages[] = Message::system($content);

        return $this;
    }

    public function setToolMessage(string $content, string $toolCallId, ?string $name = null): self
    {
        $this->messages[] = Message::tool($content, $toolCallId, $name);

        return $this;
    }

    public function setFunctionMessage(string $content, string $name, ?string $id = null): self
    {
        $this->messages[] = Message::tool($content, $id ?? uniqid('call_'), $name);

        return $this;
    }

    /**
     * Add a complete turn (user message + assistant response).
     */
    public function setMessageTurn(
        string $userMessage,
        string $assistantMessage,
        ?string $systemMessage = null,
    ): self {
        if ($systemMessage !== null) {
            $this->setSystemMessage($systemMessage);
        }
        $this->setUserMessage($userMessage);
        $this->setAssistantMessage($assistantMessage);

        return $this;
    }

    /**
     * Set the full history, replacing existing messages.
     *
     * @param  list<Message>  $messages
     */
    public function setHistory(array $messages): self
    {
        $this->messages = $messages;

        return $this;
    }

    /**
     * Add messages from an LLM response.
     */
    public function addFromOutput(GenerationResponse $output): self
    {
        $assistantMessage = $output->getAssistantMessage();
        if ($assistantMessage instanceof \LlmExe\State\Message) {
            $this->messages[] = $assistantMessage;
        }

        return $this;
    }

    /**
     * @return list<Message>
     */
    public function getHistory(): array
    {
        return $this->messages;
    }

    /**
     * Get the last message in the dialogue.
     */
    public function getLastMessage(): ?Message
    {
        return $this->messages[count($this->messages) - 1] ?? null;
    }

    /**
     * Get all messages of a specific role.
     *
     * @return list<Message>
     */
    public function getMessagesByRole(Role $role): array
    {
        return array_values(
            array_filter($this->messages, fn (Message $m): bool => $m->role === $role),
        );
    }

    /**
     * Clear all messages.
     */
    public function clear(): self
    {
        $this->messages = [];

        return $this;
    }

    /**
     * Get the number of messages.
     */
    public function count(): int
    {
        return count($this->messages);
    }
}
