<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\State;

use LlmExe\Provider\Response\GenerationResponse;
use LlmExe\State\Dialogue;
use LlmExe\State\Message;
use LlmExe\State\Role;
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
}
