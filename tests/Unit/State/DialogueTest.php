<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\State;

use HelgeSverre\Synapse\Provider\Response\GenerationResponse;
use HelgeSverre\Synapse\State\Dialogue;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\State\Role;
use PHPUnit\Framework\TestCase;

final class DialogueTest extends TestCase
{
    public function test_set_user_message(): void
    {
        $dialogue = new Dialogue;
        $dialogue->setUserMessage('Hello');

        $history = $dialogue->getHistory();
        $this->assertCount(1, $history);
        $this->assertSame(Role::User, $history[0]->role);
        $this->assertSame('Hello', $history[0]->content);
    }

    public function test_set_assistant_message(): void
    {
        $dialogue = new Dialogue;
        $dialogue->setAssistantMessage('Hi there!');

        $history = $dialogue->getHistory();
        $this->assertCount(1, $history);
        $this->assertSame(Role::Assistant, $history[0]->role);
    }

    public function test_set_system_message(): void
    {
        $dialogue = new Dialogue;
        $dialogue->setSystemMessage('You are helpful.');

        $history = $dialogue->getHistory();
        $this->assertSame(Role::System, $history[0]->role);
    }

    public function test_set_message_turn(): void
    {
        $dialogue = new Dialogue;
        $dialogue->setMessageTurn('Hi', 'Hello!', 'Be helpful');

        $history = $dialogue->getHistory();
        $this->assertCount(3, $history);
        $this->assertSame(Role::System, $history[0]->role);
        $this->assertSame(Role::User, $history[1]->role);
        $this->assertSame(Role::Assistant, $history[2]->role);
    }

    public function test_set_message_turn_without_system(): void
    {
        $dialogue = new Dialogue;
        $dialogue->setMessageTurn('Hi', 'Hello!');

        $history = $dialogue->getHistory();
        $this->assertCount(2, $history);
    }

    public function test_set_history(): void
    {
        $dialogue = new Dialogue;
        $dialogue->setUserMessage('First');
        $dialogue->setHistory([
            Message::user('Replaced'),
        ]);

        $this->assertCount(1, $dialogue->getHistory());
        $this->assertSame('Replaced', $dialogue->getHistory()[0]->content);
    }

    public function test_add_from_output(): void
    {
        $dialogue = new Dialogue;
        $response = new GenerationResponse(
            text: 'Assistant response',
            messages: [Message::assistant('Assistant response')],
            toolCalls: [],
            model: 'test',
        );

        $dialogue->addFromOutput($response);

        $this->assertCount(1, $dialogue->getHistory());
        $this->assertSame(Role::Assistant, $dialogue->getHistory()[0]->role);
    }

    public function test_get_last_message(): void
    {
        $dialogue = new Dialogue;
        $this->assertNull($dialogue->getLastMessage());

        $dialogue->setUserMessage('First');
        $dialogue->setAssistantMessage('Second');

        $this->assertSame('Second', $dialogue->getLastMessage()->content);
    }

    public function test_get_messages_by_role(): void
    {
        $dialogue = new Dialogue;
        $dialogue->setUserMessage('User 1');
        $dialogue->setAssistantMessage('Assistant 1');
        $dialogue->setUserMessage('User 2');

        $userMessages = $dialogue->getMessagesByRole(Role::User);
        $this->assertCount(2, $userMessages);
    }

    public function test_clear(): void
    {
        $dialogue = new Dialogue;
        $dialogue->setUserMessage('Test');
        $dialogue->clear();

        $this->assertCount(0, $dialogue->getHistory());
    }

    public function test_count(): void
    {
        $dialogue = new Dialogue;
        $this->assertSame(0, $dialogue->count());

        $dialogue->setUserMessage('1');
        $dialogue->setAssistantMessage('2');
        $this->assertSame(2, $dialogue->count());
    }

    public function test_name(): void
    {
        $dialogue = new Dialogue('custom-name');
        $this->assertSame('custom-name', $dialogue->getName());
    }

    public function test_fluent_interface(): void
    {
        $dialogue = (new Dialogue)
            ->setSystemMessage('Be helpful')
            ->setUserMessage('Hello')
            ->setAssistantMessage('Hi!')
            ->setUserMessage('How are you?');

        $this->assertCount(4, $dialogue->getHistory());
    }

    public function test_set_tool_message(): void
    {
        $dialogue = new Dialogue;
        $dialogue->setToolMessage('{"result": 42}', 'call_123', 'calculator');

        $history = $dialogue->getHistory();
        $this->assertCount(1, $history);
        $this->assertSame(Role::Tool, $history[0]->role);
        $this->assertSame('{"result": 42}', $history[0]->content);
        $this->assertSame('call_123', $history[0]->toolCallId);
        $this->assertSame('calculator', $history[0]->name);
    }

    public function test_set_tool_message_without_name(): void
    {
        $dialogue = new Dialogue;
        $dialogue->setToolMessage('result', 'call_456');

        $history = $dialogue->getHistory();
        $this->assertNull($history[0]->name);
        $this->assertSame('call_456', $history[0]->toolCallId);
    }

    public function test_add_tool_result(): void
    {
        $dialogue = new Dialogue;
        $toolCall = new \HelgeSverre\Synapse\Provider\Request\ToolCall(
            id: 'call_abc',
            name: 'get_weather',
            arguments: ['city' => 'Oslo'],
        );

        $dialogue->addToolResult($toolCall, ['temperature' => 20]);

        $history = $dialogue->getHistory();
        $this->assertCount(1, $history);
        $this->assertSame(Role::Tool, $history[0]->role);
        $this->assertSame('{"temperature":20}', $history[0]->content);
        $this->assertSame('call_abc', $history[0]->toolCallId);
        $this->assertSame('get_weather', $history[0]->name);
    }

    public function test_add_tool_result_with_string(): void
    {
        $dialogue = new Dialogue;
        $toolCall = new \HelgeSverre\Synapse\Provider\Request\ToolCall(
            id: 'call_xyz',
            name: 'echo',
            arguments: [],
        );

        $dialogue->addToolResult($toolCall, 'Hello World');

        $this->assertSame('Hello World', $dialogue->getHistory()[0]->content);
    }

    public function test_add_tool_results(): void
    {
        $dialogue = new Dialogue;
        $response = new \HelgeSverre\Synapse\Provider\Response\GenerationResponse(
            text: null,
            messages: [],
            toolCalls: [
                new \HelgeSverre\Synapse\Provider\Request\ToolCall('call_1', 'func_a', []),
                new \HelgeSverre\Synapse\Provider\Request\ToolCall('call_2', 'func_b', []),
            ],
            model: 'test',
        );

        $dialogue->addToolResults($response, [
            'call_1' => 'result_a',
            'call_2' => ['data' => 'result_b'],
        ]);

        $history = $dialogue->getHistory();
        $this->assertCount(2, $history);
        $this->assertSame('result_a', $history[0]->content);
        $this->assertSame('{"data":"result_b"}', $history[1]->content);
    }

    public function test_add_tool_results_skips_missing(): void
    {
        $dialogue = new Dialogue;
        $response = new \HelgeSverre\Synapse\Provider\Response\GenerationResponse(
            text: null,
            messages: [],
            toolCalls: [
                new \HelgeSverre\Synapse\Provider\Request\ToolCall('call_1', 'func_a', []),
                new \HelgeSverre\Synapse\Provider\Request\ToolCall('call_2', 'func_b', []),
            ],
            model: 'test',
        );

        $dialogue->addToolResults($response, ['call_1' => 'only_this']);

        $this->assertCount(1, $dialogue->getHistory());
    }

    public function test_execute_tool_calls(): void
    {
        $dialogue = new Dialogue;
        $response = new \HelgeSverre\Synapse\Provider\Response\GenerationResponse(
            text: null,
            messages: [],
            toolCalls: [
                new \HelgeSverre\Synapse\Provider\Request\ToolCall('call_1', 'double', ['n' => 5]),
                new \HelgeSverre\Synapse\Provider\Request\ToolCall('call_2', 'double', ['n' => 10]),
            ],
            model: 'test',
        );

        $dialogue->executeToolCalls($response, fn ($tc): int|float => $tc->arguments['n'] * 2);

        $history = $dialogue->getHistory();
        $this->assertCount(2, $history);
        $this->assertSame('10', $history[0]->content);
        $this->assertSame('20', $history[1]->content);
    }
}
