<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Prompt;

use LlmExe\Prompt\ChatPrompt;
use LlmExe\Prompt\PromptType;
use LlmExe\State\Message;
use LlmExe\State\Role;
use PHPUnit\Framework\TestCase;

final class ChatPromptTest extends TestCase
{
    public function test_add_system_message(): void
    {
        $prompt = new ChatPrompt;
        $prompt->addSystemMessage('You are a helpful assistant.');

        $messages = $prompt->render([]);

        $this->assertCount(1, $messages);
        $this->assertSame(Role::System, $messages[0]->role);
        $this->assertSame('You are a helpful assistant.', $messages[0]->content);
    }

    public function test_add_user_message(): void
    {
        $prompt = new ChatPrompt;
        $prompt->addUserMessage('Hello!');

        $messages = $prompt->render([]);

        $this->assertCount(1, $messages);
        $this->assertSame(Role::User, $messages[0]->role);
        $this->assertSame('Hello!', $messages[0]->content);
    }

    public function test_add_user_message_with_name(): void
    {
        $prompt = new ChatPrompt;
        $prompt->addUserMessage('Hello!', 'Alice');

        $messages = $prompt->render([]);

        $this->assertSame('Alice', $messages[0]->name);
    }

    public function test_add_assistant_message(): void
    {
        $prompt = new ChatPrompt;
        $prompt->addAssistantMessage('Hi there!');

        $messages = $prompt->render([]);

        $this->assertSame(Role::Assistant, $messages[0]->role);
    }

    public function test_add_tool_message(): void
    {
        $prompt = new ChatPrompt;
        $prompt->addToolMessage('{"result": 42}', 'call_123', 'calculator');

        $messages = $prompt->render([]);

        $this->assertSame(Role::Tool, $messages[0]->role);
        $this->assertSame('call_123', $messages[0]->toolCallId);
        $this->assertSame('calculator', $messages[0]->name);
    }

    public function test_system_message_with_variables(): void
    {
        $prompt = new ChatPrompt;
        $prompt->addSystemMessage('You are an expert on {{topic}}.');

        $messages = $prompt->render(['topic' => 'PHP']);

        $this->assertSame('You are an expert on PHP.', $messages[0]->content);
    }

    public function test_user_message_no_template_by_default(): void
    {
        $prompt = new ChatPrompt;
        $prompt->addUserMessage('What is {{this}}?');

        $messages = $prompt->render(['this' => 'ignored']);

        // User messages don't parse templates by default for security
        $this->assertSame('What is {{this}}?', $messages[0]->content);
    }

    public function test_user_message_with_template_enabled(): void
    {
        $prompt = new ChatPrompt;
        $prompt->addUserMessage('Hello {{name}}!', parseTemplate: true);

        $messages = $prompt->render(['name' => 'Alice']);

        $this->assertSame('Hello Alice!', $messages[0]->content);
    }

    public function test_multiple_messages(): void
    {
        $prompt = new ChatPrompt;
        $prompt
            ->addSystemMessage('Be helpful.')
            ->addUserMessage('Hi')
            ->addAssistantMessage('Hello!')
            ->addUserMessage('How are you?');

        $messages = $prompt->render([]);

        $this->assertCount(4, $messages);
        $this->assertSame(Role::System, $messages[0]->role);
        $this->assertSame(Role::User, $messages[1]->role);
        $this->assertSame(Role::Assistant, $messages[2]->role);
        $this->assertSame(Role::User, $messages[3]->role);
    }

    public function test_add_from_history(): void
    {
        $history = [
            Message::user('Previous question'),
            Message::assistant('Previous answer'),
        ];

        $prompt = new ChatPrompt;
        $prompt->addFromHistory($history);

        $messages = $prompt->render([]);

        $this->assertCount(2, $messages);
        $this->assertSame('Previous question', $messages[0]->content);
        $this->assertSame('Previous answer', $messages[1]->content);
    }

    public function test_history_placeholder(): void
    {
        $prompt = new ChatPrompt;
        $prompt
            ->addSystemMessage('You are helpful.')
            ->addHistoryPlaceholder('dialogue')
            ->addUserMessage('New question', parseTemplate: true);

        $history = [
            Message::user('Old question'),
            Message::assistant('Old answer'),
        ];

        $messages = $prompt->render([
            'dialogue' => $history,
        ]);

        $this->assertCount(4, $messages);
        $this->assertSame('You are helpful.', $messages[0]->content);
        $this->assertSame('Old question', $messages[1]->content);
        $this->assertSame('Old answer', $messages[2]->content);
        $this->assertSame('New question', $messages[3]->content);
    }

    public function test_history_placeholder_empty(): void
    {
        $prompt = new ChatPrompt;
        $prompt
            ->addHistoryPlaceholder('history')
            ->addUserMessage('Question');

        $messages = $prompt->render(['history' => []]);

        $this->assertCount(1, $messages);
    }

    public function test_history_placeholder_missing(): void
    {
        $prompt = new ChatPrompt;
        $prompt
            ->addHistoryPlaceholder('history')
            ->addUserMessage('Question');

        $messages = $prompt->render([]);

        $this->assertCount(1, $messages);
    }

    public function test_add_message(): void
    {
        $prompt = new ChatPrompt;
        $prompt->addMessage(Role::User, 'Custom message', 'CustomName');

        $messages = $prompt->render([]);

        $this->assertSame(Role::User, $messages[0]->role);
        $this->assertSame('Custom message', $messages[0]->content);
        $this->assertSame('CustomName', $messages[0]->name);
    }

    public function test_register_helper(): void
    {
        $prompt = new ChatPrompt;
        $prompt->registerHelper('shout', fn ($s) => strtoupper($s).'!');
        $prompt->addSystemMessage('{{shout greeting}}');

        $messages = $prompt->render(['greeting' => 'hello']);

        $this->assertSame('HELLO!', $messages[0]->content);
    }

    public function test_register_partial(): void
    {
        $prompt = new ChatPrompt;
        $prompt->registerPartial('rules', 'Follow these rules: be kind, be helpful.');
        $prompt->addSystemMessage('{{> rules}}');

        $messages = $prompt->render([]);

        $this->assertSame('Follow these rules: be kind, be helpful.', $messages[0]->content);
    }

    public function test_strict_mode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $prompt = new ChatPrompt;
        $prompt->strict(true);
        $prompt->addSystemMessage('Missing {{variable}}');
        $prompt->render([]);
    }

    public function test_get_type(): void
    {
        $prompt = new ChatPrompt;
        $this->assertSame(PromptType::Chat, $prompt->getType());
    }

    public function test_fluent_interface(): void
    {
        $prompt = (new ChatPrompt)
            ->addSystemMessage('System')
            ->addUserMessage('User')
            ->addAssistantMessage('Assistant');

        $this->assertInstanceOf(ChatPrompt::class, $prompt);
        $this->assertCount(3, $prompt->render([]));
    }

    public function test_complex_conversation(): void
    {
        $prompt = new ChatPrompt;
        $prompt
            ->addSystemMessage('You are {{role}}. Respond in {{language}}.')
            ->addUserMessage('Translate: Hello', parseTemplate: true)
            ->addAssistantMessage('Bonjour')
            ->addUserMessage('Translate: {{word}}', parseTemplate: true);

        $messages = $prompt->render([
            'role' => 'a translator',
            'language' => 'French',
            'word' => 'Goodbye',
        ]);

        $this->assertSame('You are a translator. Respond in French.', $messages[0]->content);
        $this->assertSame('Translate: Goodbye', $messages[3]->content);
    }
}
