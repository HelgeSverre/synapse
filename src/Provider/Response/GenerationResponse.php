<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Provider\Response;

use HelgeSverre\Synapse\Provider\Request\ToolCall;
use HelgeSverre\Synapse\State\Message;

final readonly class GenerationResponse
{
    /**
     * @param  list<Message>  $messages
     * @param  list<ToolCall>  $toolCalls
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $text,
        public array $messages,
        public array $toolCalls,
        public string $model,
        public ?UsageInfo $usage = null,
        public ?string $finishReason = null,
        public array $raw = [],
    ) {}

    public function getText(): ?string
    {
        return $this->text;
    }

    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    /** @return list<ToolCall> */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    public function getAssistantMessage(): ?Message
    {
        foreach ($this->messages as $message) {
            if ($message->role === \HelgeSverre\Synapse\State\Role::Assistant) {
                return $message;
            }
        }

        return null;
    }
}
