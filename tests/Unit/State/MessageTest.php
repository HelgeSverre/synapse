<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\State;

use HelgeSverre\Synapse\Provider\Request\ToolCall;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\State\Role;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    // ── Constructor ──────────────────────────────────────────────────

    public function test_it_can_be_constructed_directly(): void
    {
        $message = new Message(
            role: Role::User,
            content: 'Hello',
            name: 'john',
            toolCallId: null,
            metadata: ['key' => 'value'],
        );

        $this->assertSame(Role::User, $message->role);
        $this->assertSame('Hello', $message->content);
        $this->assertSame('john', $message->name);
        $this->assertNull($message->toolCallId);
        $this->assertSame(['key' => 'value'], $message->metadata);
    }

    public function test_constructor_defaults(): void
    {
        $message = new Message(Role::System, 'content');

        $this->assertNull($message->name);
        $this->assertNull($message->toolCallId);
        $this->assertSame([], $message->metadata);
    }

    // ── system() ─────────────────────────────────────────────────────

    public function test_system_creates_system_message(): void
    {
        $message = Message::system('You are a helpful assistant.');

        $this->assertSame(Role::System, $message->role);
        $this->assertSame('You are a helpful assistant.', $message->content);
        $this->assertNull($message->name);
        $this->assertNull($message->toolCallId);
        $this->assertSame([], $message->metadata);
    }

    // ── user() ───────────────────────────────────────────────────────

    public function test_user_creates_user_message(): void
    {
        $message = Message::user('Hello there');

        $this->assertSame(Role::User, $message->role);
        $this->assertSame('Hello there', $message->content);
        $this->assertNull($message->name);
    }

    public function test_user_accepts_optional_name(): void
    {
        $message = Message::user('Hello', 'alice');

        $this->assertSame('alice', $message->name);
    }

    public function test_user_name_defaults_to_null(): void
    {
        $message = Message::user('Hello');

        $this->assertNull($message->name);
    }

    // ── assistant() ──────────────────────────────────────────────────

    public function test_assistant_creates_assistant_message(): void
    {
        $message = Message::assistant('Sure, I can help.');

        $this->assertSame(Role::Assistant, $message->role);
        $this->assertSame('Sure, I can help.', $message->content);
        $this->assertNull($message->name);
        $this->assertNull($message->toolCallId);
    }

    public function test_assistant_without_tool_calls_has_empty_metadata(): void
    {
        $message = Message::assistant('Hello');

        $this->assertSame([], $message->metadata);
    }

    public function test_assistant_with_tool_calls_stores_them_in_metadata(): void
    {
        $toolCalls = [
            new ToolCall('call_1', 'get_weather', ['city' => 'Oslo']),
            new ToolCall('call_2', 'get_time', ['timezone' => 'CET']),
        ];

        $message = Message::assistant('Let me check.', $toolCalls);

        $this->assertArrayHasKey('tool_calls', $message->metadata);
        $this->assertCount(2, $message->metadata['tool_calls']);
        $this->assertSame($toolCalls, $message->metadata['tool_calls']);
    }

    public function test_assistant_with_empty_tool_calls_has_empty_metadata(): void
    {
        $message = Message::assistant('Hello', []);

        $this->assertSame([], $message->metadata);
    }

    // ── tool() ───────────────────────────────────────────────────────

    public function test_tool_creates_tool_message(): void
    {
        $message = Message::tool('{"temp": 22}', 'call_1');

        $this->assertSame(Role::Tool, $message->role);
        $this->assertSame('{"temp": 22}', $message->content);
        $this->assertSame('call_1', $message->toolCallId);
        $this->assertNull($message->name);
    }

    public function test_tool_accepts_optional_name(): void
    {
        $message = Message::tool('result', 'call_1', 'get_weather');

        $this->assertSame('get_weather', $message->name);
    }

    public function test_tool_name_defaults_to_null(): void
    {
        $message = Message::tool('result', 'call_1');

        $this->assertNull($message->name);
    }

    // ── getToolCalls() ───────────────────────────────────────────────

    public function test_get_tool_calls_returns_tool_calls_from_metadata(): void
    {
        $toolCalls = [
            new ToolCall('call_1', 'search', ['query' => 'php']),
        ];

        $message = Message::assistant('Searching...', $toolCalls);

        $result = $message->getToolCalls();

        $this->assertCount(1, $result);
        $this->assertSame('call_1', $result[0]->id);
        $this->assertSame('search', $result[0]->name);
        $this->assertSame(['query' => 'php'], $result[0]->arguments);
    }

    public function test_get_tool_calls_returns_empty_array_when_no_tool_calls(): void
    {
        $message = Message::assistant('Hello');

        $this->assertSame([], $message->getToolCalls());
    }

    public function test_get_tool_calls_returns_empty_array_for_non_assistant_messages(): void
    {
        $message = Message::user('Hello');

        $this->assertSame([], $message->getToolCalls());
    }

    // ── toArray() ────────────────────────────────────────────────────

    public function test_to_array_returns_basic_structure(): void
    {
        $message = Message::user('Hello');

        $this->assertSame([
            'role' => 'user',
            'content' => 'Hello',
        ], $message->toArray());
    }

    public function test_to_array_includes_name_when_present(): void
    {
        $message = Message::user('Hello', 'alice');

        $array = $message->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertSame('alice', $array['name']);
    }

    public function test_to_array_excludes_name_when_null(): void
    {
        $message = Message::user('Hello');

        $this->assertArrayNotHasKey('name', $message->toArray());
    }

    public function test_to_array_includes_tool_call_id_when_present(): void
    {
        $message = Message::tool('result', 'call_123', 'my_tool');

        $array = $message->toArray();

        $this->assertArrayHasKey('tool_call_id', $array);
        $this->assertSame('call_123', $array['tool_call_id']);
    }

    public function test_to_array_excludes_tool_call_id_when_null(): void
    {
        $message = Message::assistant('Hi');

        $this->assertArrayNotHasKey('tool_call_id', $message->toArray());
    }

    public function test_to_array_for_system_message(): void
    {
        $message = Message::system('Be helpful');

        $this->assertSame([
            'role' => 'system',
            'content' => 'Be helpful',
        ], $message->toArray());
    }

    public function test_to_array_for_assistant_message(): void
    {
        $message = Message::assistant('Here you go');

        $this->assertSame([
            'role' => 'assistant',
            'content' => 'Here you go',
        ], $message->toArray());
    }

    public function test_to_array_for_tool_message_with_all_fields(): void
    {
        $message = Message::tool('{"result": true}', 'call_abc', 'validator');

        $this->assertSame([
            'role' => 'tool',
            'content' => '{"result": true}',
            'name' => 'validator',
            'tool_call_id' => 'call_abc',
        ], $message->toArray());
    }

    public function test_to_array_does_not_include_metadata(): void
    {
        $toolCalls = [new ToolCall('call_1', 'fn', ['x' => 1])];
        $message = Message::assistant('Calling fn...', $toolCalls);

        $array = $message->toArray();

        $this->assertArrayNotHasKey('metadata', $array);
        $this->assertArrayNotHasKey('tool_calls', $array);
    }

    // ── Role enum values ─────────────────────────────────────────────

    public function test_each_factory_sets_correct_role(): void
    {
        $this->assertSame(Role::System, Message::system('s')->role);
        $this->assertSame(Role::User, Message::user('u')->role);
        $this->assertSame(Role::Assistant, Message::assistant('a')->role);
        $this->assertSame(Role::Tool, Message::tool('t', 'id')->role);
    }
}
