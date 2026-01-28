<?php

declare(strict_types=1);

namespace LlmExe\Prompt;

use LlmExe\State\Message;
use LlmExe\State\Role;

final class ChatPrompt extends BasePrompt
{
    /** @var list<array<string, mixed>> */
    private array $messageTemplates = [];

    public function addSystemMessage(string $content): self
    {
        $this->messageTemplates[] = [
            'role' => Role::System,
            'content' => $content,
            'name' => null,
            'parseTemplate' => true,
        ];

        return $this;
    }

    public function addUserMessage(string $content, ?string $name = null, bool $parseTemplate = false): self
    {
        $this->messageTemplates[] = [
            'role' => Role::User,
            'content' => $content,
            'name' => $name,
            'parseTemplate' => $parseTemplate,
        ];

        return $this;
    }

    public function addAssistantMessage(string $content): self
    {
        $this->messageTemplates[] = [
            'role' => Role::Assistant,
            'content' => $content,
            'name' => null,
            'parseTemplate' => true,
        ];

        return $this;
    }

    public function addToolMessage(string $content, string $toolCallId, ?string $name = null): self
    {
        $this->messageTemplates[] = [
            'role' => Role::Tool,
            'content' => $content,
            'name' => $name,
            'parseTemplate' => true,
            'toolCallId' => $toolCallId,
        ];

        return $this;
    }

    public function addMessage(Role $role, string $content, ?string $name = null, bool $parseTemplate = true): self
    {
        $this->messageTemplates[] = [
            'role' => $role,
            'content' => $content,
            'name' => $name,
            'parseTemplate' => $parseTemplate,
        ];

        return $this;
    }

    /** @param list<Message> $messages */
    public function addFromHistory(array $messages): self
    {
        foreach ($messages as $message) {
            $this->messageTemplates[] = [
                'role' => $message->role,
                'content' => $message->content,
                'name' => $message->name,
                'parseTemplate' => false,
                'toolCallId' => $message->toolCallId,
            ];
        }

        return $this;
    }

    public function addHistoryPlaceholder(string $key): self
    {
        $this->messageTemplates[] = [
            'role' => Role::User, // Placeholder, will be expanded
            'content' => "__HISTORY_PLACEHOLDER__:{$key}",
            'name' => null,
            'parseTemplate' => false,
            'isHistoryPlaceholder' => true,
            'historyKey' => $key,
        ];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<Message>
     */
    public function render(array $values = []): array
    {
        $messages = [];

        foreach ($this->messageTemplates as $template) {
            // Handle history placeholder
            if (isset($template['isHistoryPlaceholder']) && $template['isHistoryPlaceholder']) {
                $historyKey = $template['historyKey'];
                $history = $values[$historyKey] ?? [];

                if (is_array($history)) {
                    foreach ($history as $historyMessage) {
                        if ($historyMessage instanceof Message) {
                            $messages[] = $historyMessage;
                        }
                    }
                }

                continue;
            }

            $content = $template['parseTemplate']
                ? $this->renderTemplate($template['content'], $values)
                : $template['content'];

            $messages[] = new Message(
                role: $template['role'],
                content: $content,
                name: $template['name'],
                toolCallId: $template['toolCallId'] ?? null,
            );
        }

        return $messages;
    }

    public function getType(): PromptType
    {
        return PromptType::Chat;
    }
}
