<?php

declare(strict_types=1);

namespace LlmExe\Tests\Unit\Hooks;

use LlmExe\Hooks\HookDispatcher;
use PHPUnit\Framework\TestCase;

final class HookDispatcherTest extends TestCase
{
    public function test_add_listener(): void
    {
        $dispatcher = new HookDispatcher;
        $called = false;

        $dispatcher->addListener(TestEvent::class, function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($dispatcher->hasListeners(TestEvent::class));
    }

    public function test_dispatch_calls_listener(): void
    {
        $dispatcher = new HookDispatcher;
        $called = false;

        $dispatcher->addListener(TestEvent::class, function () use (&$called): void {
            $called = true;
        });

        $dispatcher->dispatch(new TestEvent('test'));

        $this->assertTrue($called);
    }

    public function test_listener_receives_correct_event_object(): void
    {
        $dispatcher = new HookDispatcher;
        $receivedEvent = null;

        $dispatcher->addListener(TestEvent::class, function (object $event) use (&$receivedEvent): void {
            $receivedEvent = $event;
        });

        $event = new TestEvent('hello');
        $dispatcher->dispatch($event);

        $this->assertSame($event, $receivedEvent);
        $this->assertSame('hello', $receivedEvent->message);
    }

    public function test_multiple_listeners_for_same_event(): void
    {
        $dispatcher = new HookDispatcher;
        $calls = [];

        $dispatcher->addListener(TestEvent::class, function () use (&$calls): void {
            $calls[] = 'first';
        });

        $dispatcher->addListener(TestEvent::class, function () use (&$calls): void {
            $calls[] = 'second';
        });

        $dispatcher->dispatch(new TestEvent('test'));

        $this->assertSame(['first', 'second'], $calls);
    }

    public function test_remove_listener(): void
    {
        $dispatcher = new HookDispatcher;
        $called = false;

        $listener = function () use (&$called): void {
            $called = true;
        };

        $dispatcher->addListener(TestEvent::class, $listener);
        $dispatcher->removeListener(TestEvent::class, $listener);
        $dispatcher->dispatch(new TestEvent('test'));

        $this->assertFalse($called);
    }

    public function test_dispatch_with_no_listeners_does_not_error(): void
    {
        $dispatcher = new HookDispatcher;

        $dispatcher->dispatch(new TestEvent('test'));

        $this->assertFalse($dispatcher->hasListeners(TestEvent::class));
    }

    public function test_has_listeners_returns_false_when_empty(): void
    {
        $dispatcher = new HookDispatcher;

        $this->assertFalse($dispatcher->hasListeners(TestEvent::class));
    }

    public function test_once_listener_fires_only_once(): void
    {
        $dispatcher = new HookDispatcher;
        $callCount = 0;

        $dispatcher->once(TestEvent::class, function () use (&$callCount): void {
            $callCount++;
        });

        $dispatcher->dispatch(new TestEvent('first'));
        $dispatcher->dispatch(new TestEvent('second'));

        $this->assertSame(1, $callCount);
    }

    public function test_clear_listeners_for_specific_event(): void
    {
        $dispatcher = new HookDispatcher;

        $dispatcher->addListener(TestEvent::class, fn () => null);
        $dispatcher->addListener(AnotherTestEvent::class, fn () => null);

        $dispatcher->clearListeners(TestEvent::class);

        $this->assertFalse($dispatcher->hasListeners(TestEvent::class));
        $this->assertTrue($dispatcher->hasListeners(AnotherTestEvent::class));
    }

    public function test_clear_all_listeners(): void
    {
        $dispatcher = new HookDispatcher;

        $dispatcher->addListener(TestEvent::class, fn () => null);
        $dispatcher->addListener(AnotherTestEvent::class, fn () => null);

        $dispatcher->clearListeners();

        $this->assertFalse($dispatcher->hasListeners(TestEvent::class));
        $this->assertFalse($dispatcher->hasListeners(AnotherTestEvent::class));
    }

    public function test_remove_listener_for_nonexistent_event_does_not_error(): void
    {
        $dispatcher = new HookDispatcher;

        $dispatcher->removeListener(TestEvent::class, fn () => null);

        $this->assertFalse($dispatcher->hasListeners(TestEvent::class));
    }

    public function test_different_event_types_are_isolated(): void
    {
        $dispatcher = new HookDispatcher;
        $testEventCalled = false;
        $anotherEventCalled = false;

        $dispatcher->addListener(TestEvent::class, function () use (&$testEventCalled): void {
            $testEventCalled = true;
        });

        $dispatcher->addListener(AnotherTestEvent::class, function () use (&$anotherEventCalled): void {
            $anotherEventCalled = true;
        });

        $dispatcher->dispatch(new TestEvent('test'));

        $this->assertTrue($testEventCalled);
        $this->assertFalse($anotherEventCalled);
    }
}

final class TestEvent
{
    public function __construct(public readonly string $message) {}
}

final class AnotherTestEvent
{
    public function __construct(public readonly int $value = 0) {}
}
