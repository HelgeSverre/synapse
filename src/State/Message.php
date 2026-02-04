<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\State;

final readonly class Message
{
    public function __construct(
        public Role $role,
        public string $content,
        public ?string $name = null,
        public ?string $toolCallId = null,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}

    public static function system(string $content): self
    {
        return new self(Role::System, $content);
    }

    public static function user(string $content, ?string $name = null): self
    {
        return new self(Role::User, $content, $name);
    }

    /**
     * @param  list<\HelgeSverre\Synapse\Provider\Request\ToolCall>  $toolCalls
     */
    public static function assistant(string $content, array $toolCalls = []): self
    {
        $metadata = [];
        if ($toolCalls !== []) {
            $metadata['tool_calls'] = $toolCalls;
        }

        return new self(Role::Assistant, $content, null, null, $metadata);
    }

    /**
     * @return list<\HelgeSverre\Synapse\Provider\Request\ToolCall>
     */
    public function getToolCalls(): array
    {
        return $this->metadata['tool_calls'] ?? [];
    }

    public static function tool(string $content, string $toolCallId, ?string $name = null): self
    {
        return new self(Role::Tool, $content, $name, $toolCallId);
    }

    /** @return array{role: string, content: string, name?: string, tool_call_id?: string} */
    public function toArray(): array
    {
        $data = [
            'role' => $this->role->value,
            'content' => $this->content,
        ];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->toolCallId !== null) {
            $data['tool_call_id'] = $this->toolCallId;
        }

        return $data;
    }
}
