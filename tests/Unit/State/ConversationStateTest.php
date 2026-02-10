<?php

declare(strict_types=1);

namespace HelgeSverre\Synapse\Tests\Unit\State;

use HelgeSverre\Synapse\State\ContextItem;
use HelgeSverre\Synapse\State\ConversationState;
use HelgeSverre\Synapse\State\Message;
use HelgeSverre\Synapse\State\Role;
use PHPUnit\Framework\TestCase;

final class ConversationStateTest extends TestCase
{
    public function test_it_can_be_created_with_defaults(): void
    {
        $state = new ConversationState();

        $this->assertSame([], $state->messages);
        $this->assertSame([], $state->context);
        $this->assertSame([], $state->attributes);
    }

    public function test_it_can_be_created_with_initial_values(): void
    {
        $messages = [Message::user('Hello')];
        $context = ['key' => new ContextItem('key', 'value')];
        $attributes = ['foo' => 'bar'];

        $state = new ConversationState($messages, $context, $attributes);

        $this->assertCount(1, $state->messages);
        $this->assertSame('Hello', $state->messages[0]->content);
        $this->assertArrayHasKey('key', $state->context);
        $this->assertSame('bar', $state->attributes['foo']);
    }

    // ── withMessage ──────────────────────────────────────────────────

    public function test_with_message_appends_a_message(): void
    {
        $state = new ConversationState();
        $message = Message::user('Hello');

        $newState = $state->withMessage($message);

        $this->assertCount(1, $newState->messages);
        $this->assertSame($message, $newState->messages[0]);
    }

    public function test_with_message_preserves_existing_messages(): void
    {
        $first = Message::system('System prompt');
        $second = Message::user('Hello');

        $state = (new ConversationState())
            ->withMessage($first)
            ->withMessage($second);

        $this->assertCount(2, $state->messages);
        $this->assertSame($first, $state->messages[0]);
        $this->assertSame($second, $state->messages[1]);
    }

    public function test_with_message_returns_new_instance(): void
    {
        $state = new ConversationState();
        $newState = $state->withMessage(Message::user('Hello'));

        $this->assertNotSame($state, $newState);
        $this->assertCount(0, $state->messages);
        $this->assertCount(1, $newState->messages);
    }

    public function test_with_message_preserves_context_and_attributes(): void
    {
        $state = (new ConversationState())
            ->withContext(new ContextItem('ctx', 'val'))
            ->withAttribute('attr', 123);

        $newState = $state->withMessage(Message::user('Hello'));

        $this->assertSame('val', $newState->getContextValue('ctx'));
        $this->assertSame(123, $newState->getAttribute('attr'));
    }

    // ── withMessages ─────────────────────────────────────────────────

    public function test_with_messages_appends_multiple_messages(): void
    {
        $state = new ConversationState();
        $messages = [
            Message::system('Be helpful'),
            Message::user('Hi'),
        ];

        $newState = $state->withMessages($messages);

        $this->assertCount(2, $newState->messages);
        $this->assertSame(Role::System, $newState->messages[0]->role);
        $this->assertSame(Role::User, $newState->messages[1]->role);
    }

    public function test_with_messages_merges_with_existing_messages(): void
    {
        $state = (new ConversationState())
            ->withMessage(Message::system('System'));

        $newState = $state->withMessages([
            Message::user('First'),
            Message::user('Second'),
        ]);

        $this->assertCount(3, $newState->messages);
        $this->assertSame('System', $newState->messages[0]->content);
        $this->assertSame('First', $newState->messages[1]->content);
        $this->assertSame('Second', $newState->messages[2]->content);
    }

    public function test_with_messages_returns_new_instance(): void
    {
        $state = new ConversationState();
        $newState = $state->withMessages([Message::user('Hi')]);

        $this->assertNotSame($state, $newState);
        $this->assertCount(0, $state->messages);
    }

    public function test_with_messages_preserves_context_and_attributes(): void
    {
        $state = (new ConversationState())
            ->withContext(new ContextItem('ctx', 'val'))
            ->withAttribute('attr', 'x');

        $newState = $state->withMessages([Message::user('Hi')]);

        $this->assertSame('val', $newState->getContextValue('ctx'));
        $this->assertSame('x', $newState->getAttribute('attr'));
    }

    // ── withContext ──────────────────────────────────────────────────

    public function test_with_context_adds_a_context_item_keyed_by_its_key(): void
    {
        $item = new ContextItem('user_id', 42, ['source' => 'auth']);
        $state = (new ConversationState())->withContext($item);

        $this->assertSame($item, $state->getContext('user_id'));
    }

    public function test_with_context_overwrites_existing_key(): void
    {
        $first = new ContextItem('key', 'old');
        $second = new ContextItem('key', 'new');

        $state = (new ConversationState())
            ->withContext($first)
            ->withContext($second);

        $this->assertSame('new', $state->getContextValue('key'));
    }

    public function test_with_context_returns_new_instance(): void
    {
        $state = new ConversationState();
        $newState = $state->withContext(new ContextItem('k', 'v'));

        $this->assertNotSame($state, $newState);
        $this->assertSame([], $state->context);
    }

    public function test_with_context_preserves_messages_and_attributes(): void
    {
        $state = (new ConversationState())
            ->withMessage(Message::user('Hi'))
            ->withAttribute('attr', true);

        $newState = $state->withContext(new ContextItem('k', 'v'));

        $this->assertCount(1, $newState->messages);
        $this->assertTrue($newState->getAttribute('attr'));
    }

    // ── withAttribute ────────────────────────────────────────────────

    public function test_with_attribute_adds_an_attribute(): void
    {
        $state = (new ConversationState())->withAttribute('temperature', 0.7);

        $this->assertSame(0.7, $state->getAttribute('temperature'));
    }

    public function test_with_attribute_overwrites_existing_key(): void
    {
        $state = (new ConversationState())
            ->withAttribute('key', 'old')
            ->withAttribute('key', 'new');

        $this->assertSame('new', $state->getAttribute('key'));
    }

    public function test_with_attribute_returns_new_instance(): void
    {
        $state = new ConversationState();
        $newState = $state->withAttribute('k', 'v');

        $this->assertNotSame($state, $newState);
        $this->assertSame([], $state->attributes);
    }

    public function test_with_attribute_preserves_messages_and_context(): void
    {
        $state = (new ConversationState())
            ->withMessage(Message::user('Hi'))
            ->withContext(new ContextItem('c', 'val'));

        $newState = $state->withAttribute('a', 1);

        $this->assertCount(1, $newState->messages);
        $this->assertSame('val', $newState->getContextValue('c'));
    }

    // ── getContext ────────────────────────────────────────────────────

    public function test_get_context_returns_item_when_found(): void
    {
        $item = new ContextItem('key', 'value', ['meta' => true]);
        $state = (new ConversationState())->withContext($item);

        $result = $state->getContext('key');

        $this->assertSame($item, $result);
        $this->assertSame('key', $result->key);
        $this->assertSame('value', $result->value);
        $this->assertSame(['meta' => true], $result->metadata);
    }

    public function test_get_context_returns_null_when_not_found(): void
    {
        $state = new ConversationState();

        $this->assertNull($state->getContext('nonexistent'));
    }

    // ── getContextValue ──────────────────────────────────────────────

    public function test_get_context_value_returns_value_when_found(): void
    {
        $state = (new ConversationState())
            ->withContext(new ContextItem('key', ['nested' => 'data']));

        $this->assertSame(['nested' => 'data'], $state->getContextValue('key'));
    }

    public function test_get_context_value_returns_default_when_not_found(): void
    {
        $state = new ConversationState();

        $this->assertSame('fallback', $state->getContextValue('missing', 'fallback'));
    }

    public function test_get_context_value_returns_null_by_default_when_not_found(): void
    {
        $state = new ConversationState();

        $this->assertNull($state->getContextValue('missing'));
    }

    // ── getAttribute ─────────────────────────────────────────────────

    public function test_get_attribute_returns_value_when_found(): void
    {
        $state = (new ConversationState())->withAttribute('model', 'gpt-4');

        $this->assertSame('gpt-4', $state->getAttribute('model'));
    }

    public function test_get_attribute_returns_default_when_not_found(): void
    {
        $state = new ConversationState();

        $this->assertSame(0.5, $state->getAttribute('temperature', 0.5));
    }

    public function test_get_attribute_returns_null_by_default_when_not_found(): void
    {
        $state = new ConversationState();

        $this->assertNull($state->getAttribute('missing'));
    }

    // ── getMessagesByRole ────────────────────────────────────────────

    public function test_get_messages_by_role_returns_matching_messages(): void
    {
        $state = (new ConversationState())
            ->withMessage(Message::system('System prompt'))
            ->withMessage(Message::user('Hello'))
            ->withMessage(Message::assistant('Hi there'))
            ->withMessage(Message::user('Follow up'));

        $userMessages = $state->getMessagesByRole(Role::User);

        $this->assertCount(2, $userMessages);
        $this->assertSame('Hello', $userMessages[0]->content);
        $this->assertSame('Follow up', $userMessages[1]->content);
    }

    public function test_get_messages_by_role_returns_empty_array_when_none_match(): void
    {
        $state = (new ConversationState())
            ->withMessage(Message::user('Hello'));

        $this->assertSame([], $state->getMessagesByRole(Role::Tool));
    }

    public function test_get_messages_by_role_returns_re_indexed_array(): void
    {
        $state = (new ConversationState())
            ->withMessage(Message::system('System'))
            ->withMessage(Message::user('User'))
            ->withMessage(Message::assistant('Assistant'));

        $assistantMessages = $state->getMessagesByRole(Role::Assistant);

        $this->assertCount(1, $assistantMessages);
        $this->assertArrayHasKey(0, $assistantMessages);
        $this->assertSame('Assistant', $assistantMessages[0]->content);
    }

    // ── getLastMessage ───────────────────────────────────────────────

    public function test_get_last_message_returns_last_message(): void
    {
        $state = (new ConversationState())
            ->withMessage(Message::user('First'))
            ->withMessage(Message::assistant('Second'))
            ->withMessage(Message::user('Third'));

        $last = $state->getLastMessage();

        $this->assertNotNull($last);
        $this->assertSame('Third', $last->content);
        $this->assertSame(Role::User, $last->role);
    }

    public function test_get_last_message_returns_null_when_empty(): void
    {
        $state = new ConversationState();

        $this->assertNull($state->getLastMessage());
    }

    // ── clear ────────────────────────────────────────────────────────

    public function test_clear_removes_all_messages(): void
    {
        $state = (new ConversationState())
            ->withMessage(Message::user('Hello'))
            ->withMessage(Message::assistant('Hi'));

        $cleared = $state->clear();

        $this->assertSame([], $cleared->messages);
    }

    public function test_clear_preserves_context(): void
    {
        $state = (new ConversationState())
            ->withContext(new ContextItem('key', 'value'))
            ->withMessage(Message::user('Hello'));

        $cleared = $state->clear();

        $this->assertSame('value', $cleared->getContextValue('key'));
    }

    public function test_clear_preserves_attributes(): void
    {
        $state = (new ConversationState())
            ->withAttribute('model', 'gpt-4')
            ->withMessage(Message::user('Hello'));

        $cleared = $state->clear();

        $this->assertSame('gpt-4', $cleared->getAttribute('model'));
    }

    public function test_clear_returns_new_instance(): void
    {
        $state = (new ConversationState())
            ->withMessage(Message::user('Hello'));

        $cleared = $state->clear();

        $this->assertNotSame($state, $cleared);
        $this->assertCount(1, $state->messages);
        $this->assertCount(0, $cleared->messages);
    }

    // ── Immutability ─────────────────────────────────────────────────

    public function test_original_state_is_never_mutated(): void
    {
        $original = new ConversationState();

        $afterMessage = $original->withMessage(Message::user('Hello'));
        $afterMessages = $original->withMessages([Message::user('A'), Message::user('B')]);
        $afterContext = $original->withContext(new ContextItem('k', 'v'));
        $afterAttribute = $original->withAttribute('a', 1);
        $afterClear = $afterMessage->clear();

        // Original remains empty
        $this->assertSame([], $original->messages);
        $this->assertSame([], $original->context);
        $this->assertSame([], $original->attributes);

        // Each derived state is independent
        $this->assertCount(1, $afterMessage->messages);
        $this->assertCount(2, $afterMessages->messages);
        $this->assertNotNull($afterContext->getContext('k'));
        $this->assertSame(1, $afterAttribute->getAttribute('a'));
        $this->assertSame([], $afterClear->messages);
    }

    public function test_chained_mutations_produce_correct_cumulative_state(): void
    {
        $state = (new ConversationState())
            ->withMessage(Message::system('System'))
            ->withContext(new ContextItem('user_id', 42))
            ->withAttribute('model', 'gpt-4')
            ->withMessage(Message::user('Hello'))
            ->withAttribute('temperature', 0.7);

        $this->assertCount(2, $state->messages);
        $this->assertSame(42, $state->getContextValue('user_id'));
        $this->assertSame('gpt-4', $state->getAttribute('model'));
        $this->assertSame(0.7, $state->getAttribute('temperature'));
    }
}
