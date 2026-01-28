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

    /**
     * Add a tool result from a ToolCall object.
     * Automatically extracts ID and name from the ToolCall.
     */
    public function addToolResult(\LlmExe\Provider\Request\ToolCall $toolCall, mixed $result): self
    {
        $content = is_string($result) ? $result : (json_encode($result) ?: '');
        $this->messages[] = Message::tool($content, $toolCall->id, $toolCall->name);

        return $this;
    }

    /**
     * Add tool results for all tool calls in a response.
     *
     * @param  array<string, mixed>  $results  Map of tool call ID to result
     */
    public function addToolResults(\LlmExe\Provider\Response\GenerationResponse $response, array $results): self
    {
        foreach ($response->getToolCalls() as $toolCall) {
            if (array_key_exists($toolCall->id, $results)) {
                $this->addToolResult($toolCall, $results[$toolCall->id]);
            }
        }

        return $this;
    }

    /**
     * Add tool results using a callable executor.
     * The callable receives the ToolCall and should return the result.
     *
     * @param  callable(\LlmExe\Provider\Request\ToolCall): mixed  $executor
     */
    public function executeToolCalls(\LlmExe\Provider\Response\GenerationResponse $response, callable $executor): self
    {
        foreach ($response->getToolCalls() as $toolCall) {
            $result = $executor($toolCall);
            $this->addToolResult($toolCall, $result);
        }

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
